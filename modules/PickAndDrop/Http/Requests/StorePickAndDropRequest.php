<?php

namespace Modules\PickAndDrop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickAndDropRequest extends FormRequest
{
    /**
     * Determine whether the customer may submit a request.
     */
    public function authorize(): bool
    {
        return $this->attributes->get('account') !== null;
    }

    /**
     * Get the validation rules for the request.
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                Rule::in(['BARANG', 'DOKUMEN']),
            ],

            'item_type' => [
                Rule::requiredIf(
                    $this->input('category') === 'BARANG'
                ),
                'nullable',
                'string',
                Rule::in([
                    'MAKANAN',
                    'BUKAN_MAKANAN',
                    'PARCEL',
                ]),
            ],

            'item_description' => [
                'required',
                'string',
                'max:5000',
            ],

            'pickup_location' => [
                'required',
                'array',
            ],

            'pickup_location.label' => [
                'required',
                'string',
                'max:255',
            ],

            'pickup_location.address' => [
                'required',
                'string',
                'max:2000',
            ],

            'pickup_contact_name' => [
                'required',
                'string',
                'max:255',
            ],

            'pickup_contact_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'delivery_location' => [
                'required',
                'array',
            ],

            'delivery_location.label' => [
                'required',
                'string',
                'max:255',
            ],

            'delivery_location.address' => [
                'required',
                'string',
                'max:2000',
            ],

            'recipient_name' => [
                'required',
                'string',
                'max:255',
            ],

            'recipient_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'additional_instructions' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * Return validated input with location snapshots.
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        $validated['pickup_location_snapshot'] =
            $validated['pickup_location'];

        $validated['delivery_location_snapshot'] =
            $validated['delivery_location'];

        unset(
            $validated['pickup_location'],
            $validated['delivery_location']
        );

        if ($validated['category'] === 'DOKUMEN') {
            $validated['item_type'] = null;
        }

        return $validated;
    }
}
