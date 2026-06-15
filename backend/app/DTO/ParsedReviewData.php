<?php

namespace App\DTO;

use Carbon\CarbonImmutable;

final class ParsedReviewData
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly ?string $authorName,
        public readonly ?CarbonImmutable $reviewDate,
        public readonly ?string $text,
        public readonly ?int $rating,
    ) {}

    /**
     * Стабильный fallback-идентификатор отзыва.
     *
     * Используется как защита от дублей, когда внешний id недоступен
     * или ненадёжен. Хэш считается от полей, формирующих смысл отзыва.
     */
    public function hash(int $organizationId): string
    {
        return hash('sha256', implode('|', [
            $organizationId,
            $this->externalId ?? '',
            $this->authorName ?? '',
            $this->reviewDate?->toDateString() ?? '',
            $this->rating ?? '',
            $this->text ?? '',
        ]));
    }
}
