<?php

namespace App\Jobs;

use App\Enums\OrganizationParseStatus;
use App\Exceptions\Parser\ParserException;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Services\OrganizationReviewSyncService;
use App\Services\Parser\YandexMapsParserInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseOrganizationReviewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Не повторяем при ошибке: повторные автоматические запросы только
     * усиливают нагрузку на внешний источник. Пользователь перезапускает вручную.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $organizationId,
    ) {}

    public function backoff(): int
    {
        return 10;
    }

    public function timeout(): int
    {
        return (int) config('yandex.parser.timeout') + 60;
    }

    /**
     * Запрет параллельного парсинга одной организации.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('parse-org-'.$this->organizationId))
                ->releaseAfter(30)
                ->expireAfter($this->timeout() + 30),
        ];
    }

    public function handle(
        YandexMapsParserInterface $parser,
        OrganizationReviewSyncService $sync,
    ): void {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $run = $organization->parseRuns()->create([
            'status' => OrganizationParseStatus::Processing->value,
            'started_at' => Carbon::now(),
        ]);

        $organization->forceFill([
            'parse_status' => OrganizationParseStatus::Processing,
            'last_error' => null,
        ])->save();

        $startedAt = microtime(true);

        Log::info('parser.run.start', [
            'organization_id' => $organization->id,
            'url' => $organization->normalized_url,
        ]);

        try {
            $data = $parser->parse($organization->source_url);
            $sync->sync($organization, $data);

            $organization->forceFill([
                'parse_status' => OrganizationParseStatus::Completed,
                'last_parsed_at' => Carbon::now(),
                'last_error' => null,
            ])->save();

            $this->finishRun($run, OrganizationParseStatus::Completed, $startedAt, [
                'reviews_found' => $data->reviewsCount ?? $data->loadedReviewsCount(),
                'reviews_saved' => $organization->fresh()->loaded_reviews_count,
            ]);

            Log::info('parser.run.completed', [
                'organization_id' => $organization->id,
                'reviews_saved' => $organization->fresh()->loaded_reviews_count,
                'partial' => $data->partial,
            ]);
        } catch (ParserException $e) {
            $this->failRun($organization, $run, $startedAt, $e->errorType(), $e->userMessage(), $e);
        } catch (Throwable $e) {
            $this->failRun(
                $organization,
                $run,
                $startedAt,
                'UnexpectedError',
                'Не удалось получить данные. Попробуйте позже.',
                $e,
            );
        }
    }

    /**
     * Сетевой/системный сбой самого job (например, таймаут):
     * не оставляем организацию навсегда в processing.
     */
    public function failed(?Throwable $exception): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null || $organization->parse_status !== OrganizationParseStatus::Processing) {
            return;
        }

        $organization->forceFill([
            'parse_status' => OrganizationParseStatus::Failed,
            'last_error' => 'Не удалось получить данные. Попробуйте позже.',
        ])->save();
    }

    private function failRun(
        Organization $organization,
        ParseRun $run,
        float $startedAt,
        string $errorType,
        string $userMessage,
        Throwable $e,
    ): void {
        $organization->forceFill([
            'parse_status' => OrganizationParseStatus::Failed,
            'last_error' => $userMessage,
        ])->save();

        $this->finishRun($run, OrganizationParseStatus::Failed, $startedAt, [
            'error_type' => $errorType,
            'error_message' => $e->getMessage(),
        ]);

        Log::warning('parser.run.failed', [
            'organization_id' => $organization->id,
            'error_type' => $errorType,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finishRun(ParseRun $run, OrganizationParseStatus $status, float $startedAt, array $attributes): void
    {
        $run->forceFill(array_merge([
            'status' => $status->value,
            'finished_at' => Carbon::now(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ], $attributes))->save();
    }
}
