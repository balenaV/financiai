<x-app-layout>
    <x-slot name="title">Editar fatura</x-slot>

    <x-page-header
        title="Editar fatura"
        :description="$bill->creditCard->name.' · fecha em '.$closingDate->format('d/m/Y')"
    />

    <form method="POST" action="{{ route('credit-card-bills.update', $bill) }}" class="surface mx-auto max-w-2xl p-6">
        @csrf
        @method('PATCH')

        <div class="rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-800">
            Esta fatura ainda está aberta. Você pode corrigir o total conforme novas compras forem lançadas.
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <span class="form-label">Referência</span>
                <p class="form-control bg-slate-50">{{ $bill->reference_month->translatedFormat('F/Y') }}</p>
            </div>
            <x-form.input label="Vencimento desta fatura" name="due_date" type="date" :value="$bill->due_date->format('Y-m-d')" />
            <x-form.select label="Ajuste manual" name="adjustment_type">
                <option value="acrescimo" @selected(bccomp($bill->adjustment_amount, '0', 2) >= 0)>Acréscimo</option>
                <option value="desconto" @selected(bccomp($bill->adjustment_amount, '0', 2) < 0)>Desconto</option>
            </x-form.select>
            <x-form.input label="Valor do ajuste" name="adjustment_amount" data-money-input inputmode="decimal" :value="ltrim($bill->adjustment_amount, '-')" />
            <x-form.input label="Motivo" name="adjustment_reason" :value="$bill->adjustment_reason" />
            <p class="text-xs text-slate-500">O ajuste substitui o anterior — não é somado a ele. Vale só para esta fatura; o cartão continua com as regras padrão.</p>
        </div>

        <div class="mt-7 flex justify-end gap-2">
            <a href="{{ route('credit-cards.show', $bill->credit_card_id) }}" class="btn-secondary">Cancelar</a>
            <x-button type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar fatura</x-button>
        </div>
    </form>
</x-app-layout>
