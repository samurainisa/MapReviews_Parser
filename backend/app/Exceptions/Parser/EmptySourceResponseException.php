<?php

namespace App\Exceptions\Parser;

/**
 * Источник открылся, но данные (рейтинг/отзывы) не найдены.
 */
class EmptySourceResponseException extends ParserException
{
    public function userMessage(): string
    {
        return 'Карточка открылась, но отзывы или рейтинг не найдены.';
    }
}
