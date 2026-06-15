<?php

namespace App\Services;

use App\DTO\ParsedOrganizationData;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сохраняет результат парсинга в БД.
 *
 * Отзывы синхронизируются через upsert по уникальному (organization_id, hash),
 * поэтому повторный парсинг не плодит дубликаты. При полном (не частичном)
 * результате отзывы, исчезнувшие из источника, удаляются; при частичном —
 * существующие сохраняются, добавляются только новые.
 */
class OrganizationReviewSyncService
{
    public function sync(Organization $organization, ParsedOrganizationData $data): void
    {
        DB::transaction(function () use ($organization, $data) {
            $organization->forceFill([
                'source_external_id' => $data->externalId ?? $organization->source_external_id,
                'title' => $data->title ?? $organization->title,
                'address' => $data->address ?? $organization->address,
                'rating' => $data->rating,
                'ratings_count' => $data->ratingsCount,
                'reviews_count' => $data->reviewsCount,
            ])->save();

            $now = Carbon::now();
            $rows = [];
            $hashes = [];

            foreach ($data->reviews as $review) {
                $hash = $review->hash($organization->id);
                $hashes[] = $hash;

                $rows[] = [
                    'organization_id' => $organization->id,
                    'source_review_id' => $review->externalId,
                    'author_name' => $review->authorName,
                    'review_date' => $review->reviewDate?->toDateString(),
                    'text' => $review->text,
                    'rating' => $this->clampRating($review->rating),
                    'hash' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    Review::upsert(
                        $chunk,
                        ['organization_id', 'hash'],
                        ['source_review_id', 'author_name', 'review_date', 'text', 'rating', 'updated_at'],
                    );
                }
            }

            // Полный результат — убираем отзывы, которых больше нет в источнике.
            if (! $data->partial) {
                $organization->reviews()
                    ->when($hashes !== [], fn ($q) => $q->whereNotIn('hash', $hashes))
                    ->delete();
            }

            $organization->forceFill([
                'loaded_reviews_count' => $organization->reviews()->count(),
            ])->save();
        });
    }

    private function clampRating(?int $rating): ?int
    {
        if ($rating === null) {
            return null;
        }

        return max(1, min(5, $rating));
    }
}
