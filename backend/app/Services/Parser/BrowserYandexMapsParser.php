<?php

namespace App\Services\Parser;

use App\DTO\ParsedOrganizationData;
use App\DTO\ParsedReviewData;
use App\Exceptions\Parser\EmptySourceResponseException;
use App\Exceptions\Parser\InvalidOrganizationUrlException;
use App\Exceptions\Parser\ParserBlockedException;
use App\Exceptions\Parser\ParserMarkupChangedException;
use App\Exceptions\Parser\SourceUnavailableException;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Парсер второго уровня (fallback).
 *
 * Запускает headless-браузер (Playwright) через отдельный node-скрипт, который
 * открывает страницу отзывов, прокручивает ленту и собирает данные после
 * выполнения JavaScript. Скрипт возвращает JSON в stdout.
 *
 * Тяжёлый browser-процесс запускается только здесь (внутри job/parser service),
 * никогда не в контроллере.
 */
class BrowserYandexMapsParser implements YandexMapsParserInterface
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = config('yandex.parser');
    }

    public function parse(string $url): ParsedOrganizationData
    {
        $mapsUrl = YandexMapsUrl::fromString($url);

        if (! $mapsUrl->looksLikeOrganizationCard()) {
            throw new InvalidOrganizationUrlException(
                'URL не похож на карточку организации: '.$mapsUrl->normalized()
            );
        }

        $payload = $this->runScript($mapsUrl->reviewsUrl());

        if (isset($payload['error'])) {
            $this->throwForScriptError((string) $payload['error']);
        }

        $reviews = array_map(
            fn (array $r): ParsedReviewData => $this->mapReview($r),
            $payload['reviews'] ?? []
        );

        $rating = isset($payload['rating']) ? (float) $payload['rating'] : null;
        $reviewsCount = isset($payload['reviewsCount']) ? (int) $payload['reviewsCount'] : null;

        if ($rating === null && $reviewsCount === null && $reviews === []) {
            throw new EmptySourceResponseException('Браузер не нашёл рейтинг и отзывы.');
        }

        $partial = $reviewsCount !== null
            && count($reviews) < min($reviewsCount, (int) $this->config['max_reviews']);

        return new ParsedOrganizationData(
            externalId: $payload['externalId'] ?? $mapsUrl->externalId(),
            title: $payload['title'] ?? null,
            address: $payload['address'] ?? null,
            rating: $rating,
            ratingsCount: isset($payload['ratingsCount']) ? (int) $payload['ratingsCount'] : null,
            reviewsCount: $reviewsCount,
            reviews: $reviews,
            partial: $partial,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function runScript(string $url): array
    {
        $script = (string) $this->config['browser_script'];

        if (! is_file($script)) {
            throw new SourceUnavailableException('Browser-скрипт не найден: '.$script);
        }

        $process = new Process(
            command: [
                (string) $this->config['node_binary'],
                $script,
                '--url='.$url,
                '--max-reviews='.$this->config['max_reviews'],
                '--scroll-delay='.$this->config['request_delay_ms'],
            ],
            timeout: (float) $this->config['timeout'] + 30.0,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new SourceUnavailableException('Browser-парсер превысил лимит времени.', 0, $e);
        }

        if (! $process->isSuccessful()) {
            throw new SourceUnavailableException(
                'Browser-скрипт завершился с ошибкой: '.trim($process->getErrorOutput())
            );
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            throw new EmptySourceResponseException('Browser-скрипт не вернул данные.');
        }

        try {
            $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new ParserMarkupChangedException('Не удалось разобрать вывод browser-скрипта.', 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function throwForScriptError(string $error): never
    {
        throw match ($error) {
            'blocked' => new ParserBlockedException('Browser-скрипт сообщил о блокировке/капче.'),
            'empty' => new EmptySourceResponseException('Browser-скрипт не нашёл данные на странице.'),
            'markup' => new ParserMarkupChangedException('Browser-скрипт не нашёл ожидаемые элементы.'),
            'invalid_url' => new InvalidOrganizationUrlException('Browser-скрипт не распознал карточку.'),
            default => new SourceUnavailableException('Browser-скрипт: '.$error),
        };
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function mapReview(array $r): ParsedReviewData
    {
        return new ParsedReviewData(
            externalId: isset($r['externalId']) ? (string) $r['externalId'] : null,
            authorName: isset($r['authorName']) ? (string) $r['authorName'] : null,
            reviewDate: $this->parseDate($r['reviewDate'] ?? null),
            text: isset($r['text']) ? (string) $r['text'] : null,
            rating: isset($r['rating']) ? (int) $r['rating'] : null,
        );
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
