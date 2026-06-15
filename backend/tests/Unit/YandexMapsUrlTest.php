<?php

namespace Tests\Unit;

use App\Services\Parser\YandexMapsUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class YandexMapsUrlTest extends TestCase
{
    private array $allowedHosts = [
        'yandex.ru', 'yandex.com', 'yandex.kz', 'yandex.by', 'yandex.uz', 'yandex.com.tr',
    ];

    public function test_accepts_allowed_host_and_subdomain(): void
    {
        $url = YandexMapsUrl::fromString('https://yandex.ru/maps/org/example/1234567890/');
        $this->assertTrue($url->hasAllowedHost($this->allowedHosts));

        $sub = YandexMapsUrl::fromString('https://maps.yandex.ru/maps/org/example/1234567890/');
        $this->assertTrue($sub->hasAllowedHost($this->allowedHosts));
    }

    public function test_rejects_fake_lookalike_domains(): void
    {
        foreach ([
            'https://yandex.ru.fake-domain.com/maps/org/x/1234567890/',
            'https://maps.yandex.ru.evil.com/maps/org/x/1234567890/',
            'https://notyandex.ru/maps/org/x/1234567890/',
        ] as $bad) {
            $url = YandexMapsUrl::fromString($bad);
            $this->assertFalse($url->hasAllowedHost($this->allowedHosts), $bad);
        }
    }

    public function test_extracts_external_id_from_various_formats(): void
    {
        $this->assertSame(
            '1234567890',
            YandexMapsUrl::fromString('https://yandex.ru/maps/org/example/1234567890/')->externalId(),
        );
        $this->assertSame(
            '1234567890',
            YandexMapsUrl::fromString('https://yandex.ru/maps/213/moscow/org/example/1234567890/reviews/')->externalId(),
        );
        $this->assertSame(
            '987654321',
            YandexMapsUrl::fromString('https://yandex.ru/maps/org/987654321/?ll=37.6')->externalId(),
        );
    }

    public function test_recognizes_organization_card(): void
    {
        $this->assertTrue(
            YandexMapsUrl::fromString('https://yandex.ru/maps/org/example/1234567890/')->looksLikeOrganizationCard(),
        );
        $this->assertFalse(
            YandexMapsUrl::fromString('https://yandex.ru/maps/?text=cafe')->looksLikeOrganizationCard(),
        );
    }

    public function test_normalizes_url_dropping_query(): void
    {
        $url = YandexMapsUrl::fromString('https://yandex.ru/maps/org/example/1234567890/reviews/?ll=37.6&z=15');
        $this->assertSame('https://yandex.ru/maps/org/example/1234567890/', $url->normalized());
    }

    public function test_throws_on_invalid_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        YandexMapsUrl::fromString('not a url');
    }
}
