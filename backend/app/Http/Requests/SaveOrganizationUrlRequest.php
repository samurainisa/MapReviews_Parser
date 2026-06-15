<?php

namespace App\Http\Requests;

use App\Services\Parser\YandexMapsUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SaveOrganizationUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_url' => [
                'required',
                'string',
                'max:'.config('yandex.max_url_length'),
                'url',
                $this->yandexCardRule(),
            ],
        ];
    }

    /**
     * Проверка, что URL — карточка организации на разрешённом домене Яндекс.Карт.
     * Разбор через parse_url, а не str_contains (защита от поддельных доменов).
     */
    private function yandexCardRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                $url = YandexMapsUrl::fromString((string) $value);
            } catch (InvalidArgumentException) {
                $fail('Введите корректную ссылку.');

                return;
            }

            if (! $url->hasAllowedHost(config('yandex.maps_hosts'))) {
                $fail('Ссылка должна вести на Яндекс.Карты.');

                return;
            }

            if (! $url->looksLikeOrganizationCard()) {
                $fail('Введите ссылку на карточку организации в Яндекс.Картах.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_url.required' => 'Введите ссылку на карточку организации в Яндекс.Картах.',
            'source_url.url' => 'Введите корректную ссылку.',
            'source_url.max' => 'Ссылка слишком длинная.',
        ];
    }
}
