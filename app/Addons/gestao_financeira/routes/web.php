<?php

use App\Addons\gestao_financeira\Http\Controllers\AccountController;
use App\Addons\gestao_financeira\Http\Controllers\CategoryController;
use App\Addons\gestao_financeira\Http\Controllers\DashboardController;
use App\Addons\gestao_financeira\Http\Controllers\ReportController;
use App\Addons\gestao_financeira\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('index');

Route::prefix('transacoes')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::get('/criar', [TransactionController::class, 'create'])->name('create');
    Route::post('/salvar', [TransactionController::class, 'store'])->name('store');
    Route::get('/{transaction}/editar', [TransactionController::class, 'edit'])->name('edit');
    Route::post('/{transaction}/atualizar', [TransactionController::class, 'update'])->name('update');
    Route::match(['delete', 'post'], '/{transaction}/excluir', [TransactionController::class, 'destroy'])->name('destroy');
    Route::post('/{transaction}/pagar', [TransactionController::class, 'pay'])->name('pay');
});

Route::prefix('categorias')->name('categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::post('/salvar', [CategoryController::class, 'store'])->name('store');
    Route::post('/{category}/atualizar', [CategoryController::class, 'update'])->name('update');
    Route::match(['delete', 'post'], '/{category}/excluir', [CategoryController::class, 'destroy'])->name('destroy');
});

Route::prefix('contas')->name('accounts.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/salvar', [AccountController::class, 'store'])->name('store');
    Route::post('/{account}/atualizar', [AccountController::class, 'update'])->name('update');
    Route::match(['delete', 'post'], '/{account}/excluir', [AccountController::class, 'destroy'])->name('destroy');
});

Route::prefix('relatorios')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/fluxo-de-caixa', [ReportController::class, 'cashFlow'])->name('cash_flow');
    Route::get('/dre', [ReportController::class, 'dre'])->name('dre');
});

Route::prefix('open-finance')->name('open_finance.')->group(function () {
    Route::get('/connect-token', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'getConnectToken'])->name('connect_token');
    Route::post('/callback', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'handleConnectCallback'])->name('callback');
    Route::post('/{connection}/vincular', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'linkAccount'])->name('link_account');
    Route::match(['delete', 'post'], '/{connection}/excluir', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'destroy'])->name('destroy');
    Route::get('/config', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'getConfig'])->name('get_config');
    Route::post('/config', [\App\Addons\gestao_financeira\Http\Controllers\OpenFinanceController::class, 'saveConfig'])->name('save_config');
});
