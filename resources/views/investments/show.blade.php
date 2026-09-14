<x-app-layout>
    <x-slot name="title">{{ $investment->name }}</x-slot><x-page-header :title="$investment->name" :description="$investment->institution.' · '.$investment->type->label()"><a href="{{ route('dashboard', ['edit_investment' => $investment->id]) }}#investimentos" class="btn-secondary">Editar</a></x-page-header>
    <div class="grid gap-4 sm:grid-cols-3"><x-financial-card label="Valor investido" :value="$investment->invested_amount" /><x-financial-card label="Valor atual" :value="$investment->current_amount" tone="primary" /><x-financial-card label="Lucro / prejuízo" :value="$metrics['profit']" :tone="bccomp($metrics['profit'], '0', 2) >= 0 ? 'positive' : 'negative'" :hint="$metrics['return_percentage'].'% de rentabilidade'" /></div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[.7fr_1.3fr]"><form method="POST" action="{{ route('investments.operations.store', $investment) }}" class="surface h-fit p-5">@csrf<h2 class="font-bold">Registrar operação</h2><div class="mt-5 space-y-4"><x-form.select label="Operação" name="type" required>@foreach($operationTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</x-form.select><x-form.input label="Valor" name="amount" data-money-input required /><x-form.input label="Quantidade" name="quantity" type="number" step="0.00000001" /><x-form.input label="Data" name="operation_date" type="date" :value="today()->format('Y-m-d')" required /><x-form.select label="Conta financeira" name="account_id"><option value="">Sem movimentar conta</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</x-form.select><x-form.textarea label="Observações" name="notes" /><x-button type="submit" class="w-full">Registrar operação</x-button><p class="text-xs text-slate-500">Aportes com conta vinculada são movimentações patrimoniais, não despesas.</p></div></form>
    <div class="surface overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="font-bold">Histórico de operações</h2>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                @foreach(['todas' => 'Todas', 'aportes' => 'Aportes', 'resgates' => 'Resgates', 'rendimentos' => 'Rendimentos'] as $key => $label)
                    <a href="{{ route('investments.show', array_filter(['investment' => $investment, 'op_type' => $key === 'todas' ? null : $key])) }}"
                       class="rounded-full border px-3 py-1.5 {{ $opType === $key ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-slate-200 text-slate-500' }}">
                        {{ $label }} ({{ $opCounts[$key] }})
                    </a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Data</th><th class="px-5 py-3">Operação</th><th class="px-5 py-3">Conta</th><th class="px-5 py-3 text-right">Valor</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($operationsPage as $operation)
                        <tr><td class="px-5 py-4">{{ $operation->operation_date->format('d/m/Y') }}</td><td class="px-5 py-4 font-semibold">{{ $operation->type->label() }}</td><td class="px-5 py-4">{{ $operation->account?->name ?? '—' }}</td><td class="px-5 py-4 text-right font-bold"><x-money :value="$operation->amount" /></td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Nenhuma movimentação neste filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($operationsPage->total() > 0)
            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm text-slate-500">
                <span>Mostrando {{ $operationsPage->firstItem() }}–{{ $operationsPage->lastItem() }} de {{ $operationsPage->total() }} movimentações</span>
                {{ $operationsPage->links() }}
            </div>
        @endif
    </div>
</div>
</x-app-layout>
