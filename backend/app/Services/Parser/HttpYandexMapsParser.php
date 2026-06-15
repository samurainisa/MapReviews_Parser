<?php

namespace App\Services\Parser;

use App\DTO\ParsedOrganizationData;
use App\DTO\ParsedReviewData;
use App\Exceptions\Parser\EmptySourceResponseException;
use App\Exceptions\Parser\InvalidOrganizationUrlException;
use App\Exceptions\Parser\ParserBlockedException;
use App\Exceptions\Parser\SourceUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Парсер первого уровня.
 *
 * Пытается получить данные через внутренние/серверные ответы Яндекс.Карт:
 *   1. Загружает страницу отзывов карточки.
 *   2. Если в HTML есть bootstrap c csrfToken/sessionId — постранично тянет
 *      отзывы через внутренний JSON-эндпоинт fetchReviews.
 *   3. Иначе разбирает серверную разметку schema.org (rating + первые отзывы).
 *
 * Формат внутренних ответов Яндекса может меняться, поэтому каждый шаг
 * защищён и при неуспехе бросает доменное исключение. Полный обход всех
 * отзывов при недоступном API берёт на себя браузерный fallback.
 */
class HttpYandexMapsParser implements YandexMapsParserInterface
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

        $html = $this->fetch($mapsUrl->reviewsUrl());
        $this->guardAgainstBlocking($html);

        [$rating, $ratingsCount, $reviewsCount, $title, $address] = $this->extractSummary($html);

        $bootstrap = $this->extractBootstrap($html, $mapsUrl->externalId());

        $reviews = $bootstrap !== null
            ? $this->fetchReviewsViaApi($mapsUrl, $bootstrap, $reviewsCount)
            : $this->extractReviewsFromHtml($html);

        // Источник открылся, но ни рейтинга, ни отзывов нет.
        if ($rating === null && $reviewsCount === null && $reviews === []) {
            throw new EmptySourceResponseException('Ни рейтинг, ни отзывы не найдены в ответе.');
        }

        $partial = $reviewsCount !== null
            && count($reviews) < min($reviewsCount, (int) $this->config['max_reviews']);

        return new ParsedOrganizationData(
            externalId: $mapsUrl->externalId(),
            title: $title,
            address: $address,
            rating: $rating,
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
            reviews: $reviews,
            partial: $partial,
        );
    }

    private function client(): PendingRequest
    {
        return Http::timeout((int) $this->config['timeout'])
            ->withHeaders([
                'User-Agent' => $this->config['user_agent'],
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ]);
    }

    private function fetch(string $url): string
    {
        try {
            $response = $this->client()->get($url);
        } catch (ConnectionException $e) {
            throw new SourceUnavailableException('Не удалось соединиться с источником.', 0, $e);
        }

        if ($response->status() === 429 || $response->status() === 403) {
            throw new ParserBlockedException('Источник вернул статус '.$response->status().'.');
        }

        if ($response->failed()) {
            throw new SourceUnavailableException('Источник вернул статус '.$response->status().'.');
        }

        $body = $response->body();

        if (trim($body) === '') {
            throw new EmptySourceResponseException('Источник вернул пустое тело ответа.');
        }

        return $body;
    }

    private function guardAgainstBlocking(string $html): void
    {
        $markers = ['SmartCaptcha', 'showcaptcha', 'Доступ ограничен', 'captcha-page', 'Подтвердите, что запросы'];

        foreach ($markers as $marker) {
            if (stripos($html, $marker) !== false) {
                throw new ParserBlockedException('Обнаружены признаки капчи/блокировки.');
            }
        }
    }

    /**
     * @return array{0: ?float, 1: ?int, 2: ?int, 3: ?string, 4: ?string}
     */
    private function extractSummary(string $html): array
    {
        $rating = $this->matchFloat('#itemprop="ratingValue"[^>]*content="([\d.,]+)"#', $html)
            ?? $this->matchFloat('#"ratingValue"\s*:\s*"?([\d.,]+)"?#', $html);

        $ratingsCount = $this->matchInt('#itemprop="(?:ratingCount|reviewCount)"[^>]*content="(\d+)"#', $html)
            ?? $this->matchInt('#"ratingCount"\s*:\s*"?(\d+)"?#', $html);

        $reviewsCount = $this->matchInt('#"reviewCount"\s*:\s*"?(\d+)"?#', $html)
            ?? $this->matchInt('#(\d+)\s*отзыв#u', $html);

        $title = $this->matchString('#itemprop="name"[^>]*content="([^"]+)"#', $html)
            ?? $this->matchString('#<meta\s+property="og:title"\s+content="([^"]+)"#', $html);

        $address = $this->matchString('#itemprop="address"[^>]*content="([^"]+)"#', $html);

        return [$rating, $ratingsCount, $reviewsCount, $title, $address];
    }

    /**
     * Извлекает данные, нужные для вызова внутреннего API отзывов.
     *
     * @return array{csrfToken: string, sessionId: ?string, businessId: string}|null
     */
    private function extractBootstrap(string $html, ?string $externalId): ?array
    {
        $csrf = $this->matchString('#"csrfToken"\s*:\s*"([^"]+)"#', $html);
        $businessId = $externalId
            ?? $this->matchString('#"businessId"\s*:\s*"?(\d+)"?#', $html);

        if ($csrf === null || $businessId === null) {
            return null;
        }

        return [
            'csrfToken' => $csrf,
            'sessionId' => $this->matchString('#"sessionId"\s*:\s*"([^"]+)"#', $html),
            'businessId' => $businessId,
        ];
    }

    /**
     * Постраничный обход внутреннего эндпоинта отзывов.
     *
     * Остановка по любому из условий: достигнут лимит max_reviews/max_iterations,
     * пустая страница, дубликаты, исчерпан список.
     *
     * @param  array{csrfToken: string, sessionId: ?string, businessId: string}  $bootstrap
     * @return ParsedReviewData[]
     */
    private function fetchReviewsViaApi(YandexMapsUrl $mapsUrl, array $bootstrap, ?int $reviewsCount): array
    {
        $endpoint = $mapsUrl->scheme.'://'.$mapsUrl->host.'/maps/api/business/fetchReviews';
        $maxReviews = (int) $this->config['max_reviews'];
        $maxIterations = (int) $this->config['max_iterations'];
        $pageSize = (int) $this->config['page_size'];
        $delayMs = (int) $this->config['request_delay_ms'];

        /** @var ParsedReviewData[] $reviews */
        $reviews = [];
        $seen = [];

        for ($page = 1; $page <= $maxIterations; $page++) {
            if (count($reviews) >= $maxReviews) {
                break;
            }

            try {
                $response = $this->client()->get($endpoint, [
                    'ajax' => 1,
                    'businessId' => $bootstrap['businessId'],
                    'csrfToken' => $bootstrap['csrfToken'],
                    'sessionId' => $bootstrap['sessionId'],
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'ranking' => 'by_time',
                ]);
            } catch (ConnectionException) {
                // Частичный результат лучше полного отказа — отдаём что есть.
                break;
            }

            if ($response->status() === 429 || $response->status() === 403) {
                throw new ParserBlockedException('API отзывов ответил статусом '.$response->status().'.');
            }

            if ($response->failed()) {
                break;
            }

            $items = $response->json('data.reviews') ?? $response->json('reviews') ?? [];

            if (! is_array($items) || $items === []) {
                break;
            }

            $newOnThisPage = 0;

            foreach ($items as $item) {
                $review = $this->mapApiReview($item);
                $key = $review->externalId ?? md5(($review->authorName ?? '').($review->text ?? ''));

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $reviews[] = $review;
                $newOnThisPage++;
            }

            // Несколько страниц подряд без новых отзывов — выходим.
            if ($newOnThisPage === 0) {
                break;
            }

            if ($reviewsCount !== null && count($reviews) >= $reviewsCount) {
                break;
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return $reviews;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapApiReview(array $item): ParsedReviewData
    {
        $author = $item['author']['name'] ?? $item['author'] ?? null;
        $timestamp = $item['updatedTime'] ?? $item['time'] ?? $item['date'] ?? null;

        return new ParsedReviewData(
            externalId: isset($item['reviewId']) ? (string) $item['reviewId'] : null,
            authorName: is_string($author) ? $author : null,
            reviewDate: $this->parseDate($timestamp),
            text: isset($item['text']) ? (string) $item['text'] : null,
            rating: isset($item['rating']) ? (int) round((float) $item['rating']) : null,
        );
    }

    /**
     * Fallback-разбор отзывов из серверной разметки schema.org.
     *
     * @return ParsedReviewData[]
     */
    private function extractReviewsFromHtml(string $html): array
    {
        if (preg_match_all('#itemprop="review"[^>]*>(.*?)</div>\s*</div>#s', $html, $blocks) === false) {
            return [];
        }

        $reviews = [];

        foreach ($blocks[1] ?? [] as $block) {
            $author = $this->matchString('#itemprop="author"[^>]*>.*?itemprop="name"[^>]*content="([^"]+)"#s', $block)
                ?? $this->matchString('#itemprop="name"[^>]*content="([^"]+)"#', $block);
            $text = $this->matchString('#itemprop="reviewBody"[^>]*>(.*?)</#s', $block);
            $date = $this->matchString('#itemprop="datePublished"[^>]*content="([^"]+)"#', $block);
            $rating = $this->matchInt('#itemprop="ratingValue"[^>]*content="(\d+)"#', $block);

            if ($author === null && $text === null) {
                continue;
            }

            $reviews[] = new ParsedReviewData(
                externalId: null,
                authorName: $author,
                reviewDate: $this->parseDate($date),
                text: $text !== null ? trim(strip_tags($text)) : null,
                rating: $rating,
            );
        }

        return $reviews;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Яндекс отдаёт unix-время в секундах (иногда в мс).
                $seconds = (int) $value;
                if ($seconds > 1_000_000_000_000) {
                    $seconds = (int) ($seconds / 1000);
                }

                return CarbonImmutable::createFromTimestamp($seconds);
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function matchString(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $m) === 1) {
            $value = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function matchInt(string $pattern, string $subject): ?int
    {
        $value = $this->matchString($pattern, $subject);

        return $value !== null ? (int) preg_replace('/\D/', '', $value) : null;
    }

    private function matchFloat(string $pattern, string $subject): ?float
    {
        $value = $this->matchString($pattern, $subject);

        return $value !== null ? (float) str_replace(',', '.', $value) : null;
    }
}
