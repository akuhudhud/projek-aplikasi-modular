<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channel = $this->input('channel');

        $contactRules = [
            'required',
            'string',
            'max:255',
        ];

        if ($channel === 'phone') {
            $contactRules[] = 'regex:/^\+?[0-9]{8,19}$/';
        } elseif ($channel === 'email') {
            $contactRules[] = 'email';
        }

        return [
            'channel' => [
                'required',
                'string',
                Rule::in([
                    'phone',
                    'email',
                ]),
            ],

            'contact' => $contactRules,

            'purpose' => [
                'required',
                'string',
                Rule::in([
                    'REGISTRATION',
                    'CHANGE_PHONE',
                    'CHANGE_EMAIL',
                ]),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $channel = $this->input('channel');
            $purpose = $this->input('purpose');

            if (
                $purpose === 'CHANGE_PHONE'
                && $channel !== 'phone'
            ) {
                $validator->errors()->add(
                    'channel',
                    'CHANGE_PHONE requires the phone channel.'
                );
            }

            if (
                $purpose === 'CHANGE_EMAIL'
                && $channel !== 'email'
            ) {
                $validator->errors()->add(
                    'channel',
                    'CHANGE_EMAIL requires the email channel.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'OTP channel is required.',
            'channel.in' => 'OTP channel is invalid.',
            'contact.required' => 'Contact information is required.',
            'contact.regex' => 'Phone number format is invalid.',
            'contact.email' => 'Email address format is invalid.',
            'purpose.required' => 'OTP purpose is required.',
            'purpose.in' => 'OTP purpose is invalid.',
        ];
    }
}
