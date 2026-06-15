<?php

namespace App\Exceptions\Parser;

/**
 * Парсер не нашёл ожидаемые поля — вероятно, изменилась структура страницы.
 */
class ParserMarkupChangedException extends ParserException
{
    public function userMessage(): string
    {
        return 'Не удалось разобрать данные карточки. '
            .'Возможно, изменилась структура страницы Яндекс.Карт.';
    }
}
