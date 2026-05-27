<?php

namespace App\Http\Requests;

use App\Enums\WorkItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_date' => ['sometimes', 'required', 'date'],
            'category' => ['sometimes', 'required', Rule::enum(WorkItemCategory::class)],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
        ];
    }
}
