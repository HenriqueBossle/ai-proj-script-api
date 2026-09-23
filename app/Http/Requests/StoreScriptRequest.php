<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScriptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'topic' => ['required', 'string', 'max:255'],
            'source_text' => ['nullable', 'string'],
            'style_text' => ['nullable', 'string'],
            'generated_content' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:pending,processing,done,failed'],
            'error_message' => ['nullable', 'string'],
        ];
    }
}
