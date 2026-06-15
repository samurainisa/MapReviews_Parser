<?php

namespace App\Services\Parser;

use App\DTO\ParsedOrganizationData;
use App\DTO\ParsedReviewData;
use App\Exceptions\Parser\EmptySourceResponseException;
use App\Exceptions\Parser\ParserMarkupChangedException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Парсер из локальных fixtures.
 *
 * Включается переменной YANDEX_PARSER_USE_FIXTURES=true и позволяет
 * прогнать весь сценарий (job → синхронизация → API → SPA) без обращения
 * к Яндексу. Также используется в тестах парсера.
 *
 * Fixture ищется по внешнему id организации:
 *   storage/app/parser-fixtures/<externalId>.json
 * При отсутствии берётся storage/app/parser-fixtures/default.json.
 */
class FixtureYandexMapsParser implements YandexMapsParserInterface
{
    public function parse(string $url): ParsedOrganizationData
    {
        $mapsUrl = YandexMapsUrl::fromString($url);
        $dir = (string) config('yandex.parser.fixtures_path');

        $candidates = array_filter([
            $mapsUrl->externalId() ? $dir.'/'.$mapsUrl->externalId().'.json' : null,
            $dir.'/default.json',
        ]);

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            throw new EmptySourceResponseException('Fixture не найден для '.$mapsUrl->normalized());
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new ParserMarkupChangedException('Некорректный fixture: '.$path, 0, $e);
        }

        $reviews = array_map(function (array $r): ParsedReviewData {
            return new ParsedReviewData(
                externalId: isset($r['externalId']) ? (string) $r['externalId'] : null,
                authorName: $r['authorName'] ?? null,
                reviewDate: isset($r['reviewDate']) ? CarbonImmutable::parse($r['reviewDate']) : null,
                text: $r['text'] ?? null,
                rating: isset($r['rating']) ? (int) $r['rating'] : null,
            );
        }, $data['reviews'] ?? []);

        return new ParsedOrganizationData(
            externalId: $data['externalId'] ?? $mapsUrl->externalId(),
            title: $data['title'] ?? null,
            address: $data['address'] ?? null,
            rating: isset($data['rating']) ? (float) $data['rating'] : null,
            ratingsCount: isset($data['ratingsCount']) ? (int) $data['ratingsCount'] : null,
            reviewsCount: isset($data['reviewsCount']) ? (int) $data['reviewsCount'] : null,
            reviews: $reviews,
            partial: (bool) ($data['partial'] ?? false),
        );
    }
}
