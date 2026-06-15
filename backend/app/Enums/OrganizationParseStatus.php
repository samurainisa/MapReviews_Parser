<?php

namespace App\Enums;

enum OrganizationParseStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Человекочитаемое описание статуса для фронтенда.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Ссылка ещё не сохранена',
            self::Pending => 'Получение данных поставлено в очередь',
            self::Processing => 'Получаем отзывы из Яндекс.Карт',
            self::Completed => 'Данные обновлены',
            self::Failed => 'Не удалось получить данные',
        };
    }

    /**
     * Статусы, при которых парсинг считается активным
     * (frontend продолжает polling).
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
