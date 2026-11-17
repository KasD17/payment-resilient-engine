<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BillingInterval;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSubscriptionRequest extends FormRequest
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
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'plan_code' => ['required', 'string', 'max:64'],
            'price' => ['required', 'integer', 'min:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
            'payment_method_id' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
