<?php

namespace App\Services\Parser;

use InvalidArgumentException;

/**
 * Разбор и нормализация ссылок на карточки организаций Яндекс.Карт.
 *
 * Намеренно не использует str_contains для проверки host — только разбор URL,
 * чтобы исключить подделки вида "yandex.ru.fake-domain.com".
 */
final class YandexMapsUrl
{
    private function __construct(
        public readonly string $original,
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $path,
    ) {}

    public static function fromString(string $url): self
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new InvalidArgumentException('URL не содержит схему или host.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Поддерживаются только http/https ссылки.');
        }

        return new self(
            original: $url,
            scheme: strtolower($parts['scheme']),
            host: strtolower($parts['host']),
            path: $parts['path'] ?? '',
        );
    }

    /**
     * host точно совпадает с разрешённым доменом или является его поддоменом.
     *
     * @param  string[]  $allowedHosts
     */
    public function hasAllowedHost(array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower($allowed);

            if ($this->host === $allowed || str_ends_with($this->host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Похоже ли это на карточку организации (а не на поиск/маршрут/категорию).
     */
    public function looksLikeOrganizationCard(): bool
    {
        return $this->externalId() !== null;
    }

    /**
     * Внешний идентификатор организации (числовой сегмент после "org/...").
     *
     * Поддерживает форматы:
     *   /maps/org/<slug>/<id>/
     *   /maps/org/<id>/
     *   /maps/<region>/org/<slug>/<id>/reviews/
     */
    public function externalId(): ?string
    {
        if (preg_match('#/org/(?:[^/]+/)?(\d{5,})#', $this->path, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Каноническая ссылка: scheme + host + путь до карточки, без query/fragment.
     */
    public function normalized(): string
    {
        $id = $this->externalId();

        if ($id !== null && preg_match('#(/maps/(?:[^/]+/)?org/[^/]+/'.$id.')#', $this->path, $m) === 1) {
            return $this->scheme.'://'.$this->host.$m[1].'/';
        }

        // Не удалось распознать каноническую форму — отдаём очищенный путь.
        return $this->scheme.'://'.$this->host.rtrim($this->path, '/').'/';
    }

    public function reviewsUrl(): string
    {
        return rtrim($this->normalized(), '/').'/reviews/';
    }
}
