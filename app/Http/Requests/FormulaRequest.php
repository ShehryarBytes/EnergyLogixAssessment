<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate checked explicitly in the controller
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:255'],
            'expression'             => ['required', 'string'],
            'variables'              => ['sometimes', 'array'],
            'variables.*.name'       => ['required', 'string', 'max:255'],
            'variables.*.expression' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                   => 'A formula name is required.',
            'expression.required'             => 'A formula expression is required.',
            'variables.*.name.required'       => 'Each calculated variable must have a name.',
            'variables.*.expression.required' => 'Each calculated variable must have an expression.',
        ];
    }
}
