<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                'required_without:email',
                'unique:accounts,phone',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
                'unique:accounts,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'max:12',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
            ],

            'password_confirmation' => [
                'required',
                'same:password',
            ],
        ];
    }
}
