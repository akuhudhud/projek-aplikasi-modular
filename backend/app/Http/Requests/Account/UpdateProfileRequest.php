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
                'nullable',
                'string',
                'max:30',
                'required_without:email',
                Rule::unique('accounts', 'phone')->ignore($account?->id),
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                'required_without:phone',
                Rule::unique('accounts', 'email')->ignore($account?->id),
            ],
        ];
    }
}
