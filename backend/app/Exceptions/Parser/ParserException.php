<?php

namespace App\Exceptions\Parser;

use RuntimeException;

/**
 * Базовая доменная ошибка парсера.
 *
 * Несёт два слоя информации:
 *  - userMessage() — понятный текст для пользователя (без технических деталей);
 *  - errorType()   — короткий машинный код для логов и таблицы parse_runs.
 *
 * Технические подробности уходят в getMessage()/getPrevious() и в логи,
 * но никогда не показываются пользователю напрямую.
 */
abstract class ParserException extends RuntimeException
{
    abstract public function userMessage(): string;

    public function errorType(): string
    {
        return class_basename(static::class);
    }
}
