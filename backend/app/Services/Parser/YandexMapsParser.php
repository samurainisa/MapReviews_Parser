<?php

namespace App\Services\Parser;

use App\DTO\ParsedOrganizationData;
use App\Exceptions\Parser\InvalidOrganizationUrlException;
use App\Exceptions\Parser\ParserException;
use Illuminate\Support\Facades\Log;

/**
 * Стратегия парсинга: HTTP-first + browser fallback.
 *
 * Порядок:
 *   1. Если включены fixtures — берём данные из них (демо/тесты).
 *   2. Иначе пробуем HTTP-парсер (быстрый, стабильный, легко тестируется).
 *   3. Если HTTP бросил доменную ошибку ИЛИ вернул пустой/неполный результат
 *      (нашёл счётчики, но не получил отзывы) — и включён browser fallback —
 *      пробуем headless-браузер.
 *
 * InvalidOrganizationUrlException детерминирована и не уходит в fallback.
 *
 * Это единственный класс, который привязан к интерфейсу в контейнере;
 * остальные парсеры — его детали реализации.
 */
class YandexMapsParser implements YandexMapsParserInterface
{
    public function __construct(
        private readonly HttpYandexMapsParser $httpParser,
        private readonly BrowserYandexMapsParser $browserParser,
        private readonly FixtureYandexMapsParser $fixtureParser,
    ) {}

    public function parse(string $url): ParsedOrganizationData
    {
        if ((bool) config('yandex.parser.use_fixtures')) {
            return $this->fixtureParser->parse($url);
        }

        $browserEnabled = (bool) config('yandex.parser.browser_fallback_enabled');

        try {
            $result = $this->httpParser->parse($url);

            if (! $browserEnabled || ! $this->shouldEscalate($result)) {
                return $result;
            }

            Log::info('parser.escalate_to_browser', [
                'reason' => 'incomplete_http_result',
                'loaded' => $result->loadedReviewsCount(),
                'expected' => $result->reviewsCount,
            ]);

            return $this->tryBrowserOrFallback($url, $result);
        } catch (InvalidOrganizationUrlException $e) {
            throw $e;
        } catch (ParserException $e) {
            Log::warning('parser.http_failed', [
                'error_type' => $e->errorType(),
                'message' => $e->getMessage(),
            ]);

            if (! $browserEnabled) {
                throw $e;
            }

            return $this->tryBrowserOrFallback($url, null, $e);
        }
    }

    /**
     * HTTP дал ответ, но отзывы получить не удалось при ненулевом счётчике.
     */
    private function shouldEscalate(ParsedOrganizationData $result): bool
    {
        return ($result->reviewsCount ?? 0) > 0 && $result->loadedReviewsCount() === 0;
    }

    /**
     * Пробует браузер; при его неудаче возвращает результат HTTP, если он был,
     * иначе пробрасывает исходную доменную ошибку.
     */
    private function tryBrowserOrFallback(
        string $url,
        ?ParsedOrganizationData $httpResult,
        ?ParserException $httpError = null,
    ): ParsedOrganizationData {
        try {
            return $this->browserParser->parse($url);
        } catch (ParserException $browserError) {
            Log::warning('parser.browser_failed', [
                'error_type' => $browserError->errorType(),
                'message' => $browserError->getMessage(),
            ]);

            if ($httpResult !== null) {
                return $httpResult;
            }

            throw $httpError ?? $browserError;
        }
    }
}
