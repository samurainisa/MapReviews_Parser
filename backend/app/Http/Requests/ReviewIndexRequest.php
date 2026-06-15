<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewIndexRequest extends FormRequest
{
    /**
     * per_page зафиксирован на 50 (см. PRD 13.7) — параметр клиента игнорируется.
     */
    public const PER_PAGE = 50;

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
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function page(): int
    {
        return max(1, (int) $this->query('page', 1));
    }
}
