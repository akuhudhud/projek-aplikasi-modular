<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_id' => [
                'required',
                'uuid',
            ],
            'otp' => [
                'required',
                'digits:6',
            ],
        ];
    }
}
