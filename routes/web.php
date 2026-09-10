<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditCardBillController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\FinancialGoalController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReauthenticationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'audit'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::patch('accounts/{account}/archive', [AccountController::class, 'archive'])->name('accounts.archive');
    Route::patch('accounts/{account}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
    Route::resource('accounts', AccountController::class)->except(['index', 'create', 'edit']);
    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'index', 'edit']);
    Route::get('transactions/export/csv', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('transactions/export/ofx', [TransactionController::class, 'exportOfx'])->name('transactions.export.ofx');
    Route::get('transactions/import', [TransactionImportController::class, 'create'])->name('transactions.import.create');
    Route::post('transactions/import', [TransactionImportController::class, 'store'])->name('transactions.import.store');
    Route::post('transactions/import/{batch}/preview', [TransactionImportController::class, 'preview'])->name('transactions.import.preview');
    Route::get('transactions/import/{batch}', [TransactionImportController::class, 'show'])->name('transactions.import.show');
    Route::post('transactions/import/{batch}/commit', [TransactionImportController::class, 'commit'])->name('transactions.import.commit');
    Route::post('transactions/import/{batch}/revert', [TransactionImportController::class, 'revert'])->name('transactions.import.revert');
    Route::post('transactions/{transaction}/duplicate', [TransactionController::class, 'duplicate'])->name('transactions.duplicate');
    Route::patch('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::resource('transactions', TransactionController::class)->except(['show', 'create', 'edit', 'index']);

    Route::post('credit-cards/{credit_card}/bills', [CreditCardBillController::class, 'store'])->name('credit-cards.bills.store');
    Route::get('credit-card-bills/{bill}/edit', [CreditCardBillController::class, 'edit'])->name('credit-card-bills.edit');
    Route::patch('credit-card-bills/{bill}', [CreditCardBillController::class, 'update'])->name('credit-card-bills.update');
    Route::post('credit-card-bills/{bill}/pay', [CreditCardBillController::class, 'pay'])->name('credit-card-bills.pay');
    Route::delete('credit-card-bills/{bill}', [CreditCardBillController::class, 'destroy'])->name('credit-card-bills.destroy');
    Route::resource('credit-cards', CreditCardController::class)->except(['index', 'create', 'edit']);

    Route::post('debt-installments/{installment}/pay', [DebtController::class, 'pay'])->name('debts.installments.pay');
    Route::resource('debts', DebtController::class)->except(['index', 'create', 'edit', 'show']);

    Route::post('investments/{investment}/operations', [InvestmentController::class, 'operation'])->name('investments.operations.store');
    Route::resource('investments', InvestmentController::class)->except(['index', 'create', 'edit']);

    Route::post('budgets/copy-previous', [BudgetController::class, 'copy'])->name('budgets.copy');
    Route::resource('budgets', BudgetController::class)->except(['create', 'show', 'index', 'edit']);
    Route::post('goals/{goal}/contribute', [FinancialGoalController::class, 'contribute'])->middleware('throttle:30,1')->name('goals.contribute');
    Route::resource('goals', FinancialGoalController::class)->except(['show', 'index', 'create', 'edit']);

    Route::get('reports/export/csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('profile.destroy');
    Route::patch('/profile/sessions/logout-other', [ProfileController::class, 'logoutOtherSessions'])->middleware('throttle:5,1')->name('profile.logout-other-sessions');
    // 5/min como no password.update: para quem já tem a sessão, este endpoint
    // é um oráculo de senha online, e o limite frouxo daria milhares de
    // tentativas por dia com resposta perfeitamente distinguível.
    Route::post('/settings/reauthenticate', [ReauthenticationController::class, 'store'])->middleware('throttle:5,1')->name('reauthenticate');
    Route::post('/settings/two-factor', [TwoFactorAuthenticationController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.enable');
    Route::post('/settings/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::post('/settings/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->middleware('throttle:5,1')->name('two-factor.recovery-codes');
    Route::delete('/settings/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])->middleware('throttle:5,1')->name('two-factor.disable');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/sections', [SettingsController::class, 'updateSections'])->name('settings.sections');
    Route::patch('/settings/toggle-values', [SettingsController::class, 'toggleValues'])->name('settings.toggle-values');
    Route::patch('/settings/toggle-theme', [SettingsController::class, 'toggleTheme'])->name('settings.toggle-theme');
});

require __DIR__.'/auth.php';
