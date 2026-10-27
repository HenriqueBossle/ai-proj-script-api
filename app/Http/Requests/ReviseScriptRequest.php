<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviseScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instruction.required' => 'A instrução de revisão é obrigatória.',
            'instruction.string' => 'A instrução de revisão deve ser um texto.',
            'instruction.max' => 'A instrução de revisão não pode ter mais de 20.000 caracteres.',
        ];
    }
}
