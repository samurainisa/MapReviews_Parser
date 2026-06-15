<?php

namespace App\Models;

use App\Enums\OrganizationParseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'user_id',
        'source_url',
        'normalized_url',
        'source_external_id',
        'title',
        'address',
        'rating',
        'ratings_count',
        'reviews_count',
        'loaded_reviews_count',
        'parse_status',
        'last_error',
        'last_parsed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'loaded_reviews_count' => 'integer',
            'parse_status' => OrganizationParseStatus::class,
            'last_parsed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<ParseRun, $this>
     */
    public function parseRuns(): HasMany
    {
        return $this->hasMany(ParseRun::class);
    }
}
