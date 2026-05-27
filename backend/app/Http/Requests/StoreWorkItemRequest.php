<?php

namespace App\Http\Requests;

use App\Enums\WorkItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'category' => ['required', Rule::enum(WorkItemCategory::class)],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
