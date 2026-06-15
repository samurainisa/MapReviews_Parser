<?php

namespace App\Actions\Organization;

use App\Enums\OrganizationParseStatus;
use App\Jobs\ParseOrganizationReviewsJob;
use App\Models\Organization;

/**
 * Переводит организацию в статус pending, очищает прошлую ошибку и
 * ставит задачу парсинга в очередь. Используется и при сохранении ссылки,
 * и при ручном обновлении (refresh).
 */
class StartOrganizationParsingAction
{
    public function execute(Organization $organization): Organization
    {
        $organization->forceFill([
            'parse_status' => OrganizationParseStatus::Pending,
            'last_error' => null,
        ])->save();

        ParseOrganizationReviewsJob::dispatch($organization->id);

        return $organization;
    }
}
