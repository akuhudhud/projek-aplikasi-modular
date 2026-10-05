<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->attributes->get('account');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('accounts', 'phone')->ignore($account?->id),
            ],

            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('accounts', 'email')->ignore($account?->id),
            ],
        ];
    }
}
