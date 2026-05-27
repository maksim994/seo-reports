<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'promotion_start_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название проекта.',
        ];
    }
}
