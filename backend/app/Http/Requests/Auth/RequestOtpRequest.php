<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => [
                'required',
                'string',
                Rule::in(['phone', 'email']),
            ],
            'contact' => [
                'required',
                'string',
                'max:255',
            ],
            'purpose' => [
                'required',
                'string',
                Rule::in([
                    'REGISTRATION',
                    'PASSWORD_RESET',
                ]),
            ],
        ];
    }
}
