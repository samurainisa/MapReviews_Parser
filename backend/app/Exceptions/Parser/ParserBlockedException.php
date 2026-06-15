<?php

namespace App\Exceptions\Parser;

/**
 * Признаки блокировки/капчи со стороны внешнего источника.
 */
class ParserBlockedException extends ParserException
{
    public function userMessage(): string
    {
        return 'Внешний источник временно ограничил получение данных. '
            .'Попробуйте позже.';
    }
}
