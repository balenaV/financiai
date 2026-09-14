<x-app-layout>
    <x-slot name="title">{{ $account->name }}</x-slot>
    <x-page-header :title="$account->name" :description="$account->institution ?: $account->type->label()">
        <a href="{{ route('dashboard', ['edit_account' => $account->id]) }}#contas" class="btn-secondary">Editar conta</a>
        <a href="{{ route('dashboard') }}" class="btn-primary">Nova transação</a>
    </x-page-header>
    <div class="grid gap-4 sm:grid-cols-2"><x-financial-card label="Saldo atual" :value="$current" tone="primary" /><x-financial-card label="Saldo projetado" :value="$projected" /></div>
    <div class="surface mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="font-bold">Extrato da conta</h2>
            <form method="GET" class="flex flex-wrap items-center gap-3">
                @if($stmtType !== 'todas')
                    <input type="hidden" name="stmt_type" value="{{ $stmtType }}">
                @endif
                <input type="text" name="stmt_search" value="{{ $stmtSearch }}" placeholder="Buscar por descrição" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                <button type="submit" class="sr-only">Buscar</button>
            </form>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                @foreach(['todas' => 'Tudo', 'entradas' => 'Entradas', 'saidas' => 'Saídas'] as $key => $label)
                    <a href="{{ route('accounts.show', array_filter(['account' => $account, 'stmt_type' => $key === 'todas' ? null : $key, 'stmt_search' => $stmtSearch ?: null])) }}"
                       class="rounded-full border px-3 py-1.5 {{ $stmtType === $key ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-slate-200 text-slate-500' }}">
                        {{ $label }} ({{ $stmtCounts[$key] }})
                    </a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Data</th><th class="px-5 py-3">Descrição</th><th class="px-5 py-3 text-right">Valor</th><th class="px-5 py-3 text-right">Saldo após</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($statementPage as $row)
                        <tr>
                            <td class="px-5 py-4 whitespace-nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 font-medium">{{ $row['description'] }}</td>
                            <td class="px-5 py-4 text-right font-bold {{ bccomp($row['amount'], '0', 2) >= 0 ? 'text-success-700' : 'text-danger-700' }}"><x-money :value="$row['amount']" /></td>
                            <td class="px-5 py-4 text-right"><x-money :value="$row['balance_after']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Nenhuma movimentação nesse filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($statementPage->total() > 0)
            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm text-slate-500">
                <span>Mostrando {{ $statementPage->firstItem() }}–{{ $statementPage->lastItem() }} de {{ $statementPage->total() }} movimentações</span>
                {{ $statementPage->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
