<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScriptRequest extends FormRequest
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
            'topic'       => ['sometimes', 'required', 'string', 'max:255'],
            'source_text' => ['sometimes', 'required', 'string', 'max:20000'],
            'style_text'  => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic.required'       => 'O tema é obrigatório.',
            'topic.string'         => 'O tema deve ser um texto.',
            'topic.max'            => 'O tema não pode ter mais de 255 caracteres.',
            'source_text.required' => 'A fonte de informações é obrigatória.',
            'source_text.string'   => 'A fonte de informações deve ser um texto.',
            'source_text.max'      => 'A fonte de informações não pode ter mais de 20.000 caracteres.',
            'style_text.string'    => 'O estilo de escrita deve ser um texto.',
            'style_text.max'       => 'O estilo de escrita não pode ter mais de 20.000 caracteres.',
        ];
    }
}
