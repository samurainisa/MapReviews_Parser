<?php

namespace App\DTO;

final class ParsedOrganizationData
{
    /**
     * @param  ParsedReviewData[]  $reviews
     */
    public function __construct(
        public readonly ?string $externalId,
        public readonly ?string $title,
        public readonly ?string $address,
        public readonly ?float $rating,
        public readonly ?int $ratingsCount,
        public readonly ?int $reviewsCount,
        public readonly array $reviews,
        /**
         * Признак частичного результата: счётчики получены,
         * но удалось загрузить не все отзывы.
         */
        public readonly bool $partial = false,
    ) {}

    public function loadedReviewsCount(): int
    {
        return count($this->reviews);
    }
}
