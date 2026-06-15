<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParseRun extends Model
{
    protected $fillable = [
        'organization_id',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_type',
        'error_message',
        'reviews_found',
        'reviews_saved',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'reviews_found' => 'integer',
            'reviews_saved' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
