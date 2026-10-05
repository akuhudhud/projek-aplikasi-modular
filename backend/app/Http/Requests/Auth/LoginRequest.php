<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'required_without:email',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
            ],

            'password' => [
                'required',
                'string',
            ],
        ];
    }
}
