<?php

namespace App\Actions\Organization;

use App\Models\Organization;
use App\Models\User;
use App\Services\Parser\YandexMapsUrl;

/**
 * Сохраняет (создаёт или обновляет) ссылку на карточку организации
 * пользователя и запускает получение данных.
 *
 * В рамках MVP у пользователя одна организация: повторное сохранение
 * обновляет существующую запись.
 */
class SaveOrganizationSourceAction
{
    public function __construct(
        private readonly StartOrganizationParsingAction $startParsing,
    ) {}

    public function execute(User $user, string $sourceUrl): Organization
    {
        $mapsUrl = YandexMapsUrl::fromString($sourceUrl);

        $organization = $user->organizations()->firstOrNew([]);

        $organization->fill([
            'source_url' => $sourceUrl,
            'normalized_url' => $mapsUrl->normalized(),
            'source_external_id' => $mapsUrl->externalId(),
        ]);
        $organization->save();

        $this->startParsing->execute($organization);

        return $organization->refresh();
    }
}
