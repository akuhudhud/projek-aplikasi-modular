<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class CompleteContactChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_token' => [
                'required',
                'string',
                'min:64',
                'max:64',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'verification_token.required' =>
                'Verification token is required.',
            'verification_token.min' =>
                'Verification token is invalid.',
            'verification_token.max' =>
                'Verification token is invalid.',
        ];
    }
}
