<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditCardBillUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['nullable', 'date'],
            'adjustment_type' => ['nullable', 'in:acrescimo,desconto'],
            'adjustment_amount' => ['nullable', 'regex:/^[\d.,\s]*$/'],
            'adjustment_reason' => ['nullable', 'string', 'max:255', 'required_with:adjustment_amount'],
        ];
    }
}
