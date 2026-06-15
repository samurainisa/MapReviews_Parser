<?php

namespace App\Exceptions\Parser;

/**
 * Внешний источник не открылся, вернул ошибку или не отвечает.
 */
class SourceUnavailableException extends ParserException
{
    public function userMessage(): string
    {
        return 'Карточка организации временно недоступна. '
            .'Попробуйте обновить данные позже.';
    }
}
