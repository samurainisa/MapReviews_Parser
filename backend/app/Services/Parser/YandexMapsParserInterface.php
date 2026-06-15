<?php

namespace App\Services\Parser;

use App\DTO\ParsedOrganizationData;
use App\Exceptions\Parser\ParserException;

interface YandexMapsParserInterface
{
    /**
     * Получить структурированные данные карточки организации.
     *
     * @throws ParserException при доменных ошибках (недоступность, пустой
     *                         ответ, изменение разметки, блокировка и т.п.)
     */
    public function parse(string $url): ParsedOrganizationData;
}
