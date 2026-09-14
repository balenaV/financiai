<?php

namespace App\Http\Requests;

use App\Enums\CategoryType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('payment_channel')) {
            $this->merge(['payment_channel' => 'account']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownedAccount = Rule::exists('accounts', 'id')->where(fn ($query) => $query
            ->where('user_id', $this->user()->id)->whereNull('deleted_at'));

        return [
            'payment_channel' => ['required', Rule::in(['account', 'credit_card'])],
            'account_id' => ['nullable', 'required_unless:payment_channel,credit_card', $ownedAccount],
            'credit_card_id' => [
                'nullable',
                'required_if:payment_channel,credit_card',
                Rule::exists('credit_cards', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)->where('active', true)),
            ],
            'destination_account_id' => [
                'nullable', 'required_if:type,'.TransactionType::Transfer->value,
                'different:account_id',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->whereNull('deleted_at')),
            ],
            'category_id' => [
                'nullable', 'required_unless:type,'.TransactionType::Transfer->value,
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'regex:/^[\d.,\s]{1,25}$/', 'not_in:0,0.00,0,00'],
            'competence_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(TransactionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_mode' => ['nullable', Rule::in(['single', 'installment'])],
            'installment_count' => ['nullable', 'required_if:payment_mode,installment', 'integer', 'min:2', 'max:240'],
            'first_installment_date' => ['nullable', 'required_if:payment_mode,installment', 'date'],
            'recurrence_count' => ['nullable', 'integer', 'min:1', 'max:120'],
            // Sem tela no produto que colete essa data separada da
            // competence_date — TransactionService::create já cai para
            // competence_date quando ausente (era exigido sem nunca ser
            // enviado, o que travava toda assinatura nova no modal).
            'recurrence_start_date' => ['nullable', 'date'],
            'update_future' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('payment_mode') === 'installment' && (int) $this->input('recurrence_count', 1) > 1) {
                    $validator->errors()->add('recurrence_count', 'Parcelamento e recorrência não podem ser usados juntos.');
                }

                if ($this->input('payment_channel') === 'credit_card'
                    && $this->input('type') !== TransactionType::Expense->value) {
                    $validator->errors()->add('payment_channel', 'Cartão de crédito só pode ser usado em despesas.');
                }

                if ($this->input('type') !== TransactionType::Transfer->value && $this->filled('category_id')) {
                    $category = Category::query()->find($this->input('category_id'));
                    $expected = $this->input('type') === TransactionType::Income->value
                        ? CategoryType::Income
                        : CategoryType::Expense;

                    if (! $category || $category->type !== $expected) {
                        $validator->errors()->add('category_id', 'A categoria não corresponde ao tipo da transação.');
                    }
                }
            },
        ];
    }
}
