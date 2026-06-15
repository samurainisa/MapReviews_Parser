<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partial = $this->reviews_count !== null
            && $this->loaded_reviews_count < $this->reviews_count;

        return [
            'id' => $this->id,
            'source_url' => $this->source_url,
            'normalized_url' => $this->normalized_url,
            'title' => $this->title,
            'address' => $this->address,
            'parse_status' => $this->parse_status->value,
            'parse_status_label' => $this->parse_status->label(),
            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'loaded_reviews_count' => $this->loaded_reviews_count,
            'is_partial' => $partial,
            'last_parsed_at' => $this->last_parsed_at?->toIso8601String(),
            'last_error' => $this->last_error,
        ];
    }
}
