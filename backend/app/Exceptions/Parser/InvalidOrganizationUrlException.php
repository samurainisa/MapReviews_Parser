<?php

namespace App\Exceptions\Parser;

/**
 * Ссылка не является карточкой организации (не удалось определить карточку).
 */
class InvalidOrganizationUrlException extends ParserException
{
    public function userMessage(): string
    {
        return 'Не удалось определить карточку организации по этой ссылке. '
            .'Проверьте ссылку и попробуйте ещё раз.';
    }
}
