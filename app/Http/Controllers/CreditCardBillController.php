<?php

namespace App\Http\Controllers;

use App\Enums\CreditCardBillStatus;
use App\Http\Requests\CreditCardBillRequest;
use App\Http\Requests\CreditCardBillUpdateRequest;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Services\CreditCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardBillController extends Controller
{
    public function store(CreditCardBillRequest $request, CreditCard $creditCard, CreditCardService $service): RedirectResponse
    {
        $this->authorize('update', $creditCard);
        $service->createBill($request->user(), $creditCard, $request->validated());

        return back()->with('success', 'Fatura mensal registrada.');
    }

    public function pay(Request $request, CreditCardBill $bill, CreditCardService $service): RedirectResponse
    {
        $this->authorize('update', $bill);
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'paid_at' => ['nullable', 'date'],
        ]);
        $account = $request->user()->accounts()->findOrFail($data['account_id']);
        $service->pay($request->user(), $bill, $account, $data['paid_at'] ?? null);

        return back()->with('success', 'Fatura paga e despesa registrada.');
    }

    public function edit(CreditCardBill $bill, CreditCardService $service): View
    {
        $this->authorize('update', $bill);

        abort_unless($service->canEdit($bill), 422, 'Esta fatura já fechou e não pode mais ser editada.');

        return view('credit-cards.bill-form', [
            'bill' => $bill->load('creditCard'),
            'closingDate' => $service->closingDate($bill),
        ]);
    }

    public function update(
        CreditCardBillUpdateRequest $request,
        CreditCardBill $bill,
        CreditCardService $service,
    ): RedirectResponse {
        $this->authorize('update', $bill);
        $service->updateOpenBill($request->user(), $bill, $request->validated());

        // O modal novo (dashboard, aba Cartões) e a página cheia antiga
        // (credit-cards.show → bill-form) postam para a mesma rota. Quem veio
        // da página cheia volta para ela — perder esse contexto seria uma
        // regressão para quem ainda chega por um link de lembrete de fatura
        // (ver App\Console\Commands\SendFinancialReminders). Quem veio do
        // modal cai na aba Cartões do painel, que é onde ele já estava.
        if (str_contains((string) $request->headers->get('referer'), '/credit-cards/')) {
            return redirect()->route('credit-cards.show', $bill->credit_card_id)->with('success', 'Fatura atualizada.');
        }

        return redirect(route('dashboard').'#cartoes')->with('success', 'Fatura atualizada.');
    }

    public function destroy(CreditCardBill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);

        if ($bill->status === CreditCardBillStatus::Paid || $bill->transaction_id) {
            return back()->with('error', 'Faturas pagas não podem ser excluídas.');
        }

        if ($bill->purchases()->exists()) {
            return back()->with('error', 'Faturas com compras vinculadas não podem ser excluídas.');
        }

        $bill->delete();

        return back()->with('success', 'Fatura excluída.');
    }
}
