<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChargePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:50'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'payment_method_id' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
