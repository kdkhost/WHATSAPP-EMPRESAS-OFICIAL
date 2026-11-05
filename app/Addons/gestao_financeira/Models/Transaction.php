<?php

namespace App\Addons\gestao_financeira\Models;

use App\Addons\gestao_financeira\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use TenantScoped;
    /**
     * Tabela associada ao modelo.
     */
    protected $table = 'addon_gestao_financeira_transactions';

    /**
     * Atributos atribuíveis em massa.
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'description',
        'amount',
        'due_date',
        'payment_date',
        'status',
        'account_id',
        'category_id',
        'notes',
        'attachment_path',
        'provider_transaction_id',
    ];

    /**
     * Casts de tipos de atributos.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
    ];

    /**
     * Categoria associada a esta transação.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Conta/Carteira de onde a transação partiu ou entrou.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Mutator executado ao salvar transações.
     * Atualiza o saldo atual da conta vinculada dependendo do status e do tipo de lançamento.
     */
    protected static function booted()
    {
        // Ao salvar transação com status 'pago', atualiza o saldo
        static::created(function (Transaction $transaction) {
            if ($transaction->status === 'pago' && $transaction->account) {
                $account = $transaction->account;
                if ($transaction->type === 'receita') {
                    $account->increment('current_balance', $transaction->amount);
                } elseif ($transaction->type === 'despesa') {
                    $account->decrement('current_balance', $transaction->amount);
                }
            }
        });

        // Ao deletar transações pagas, devolve o saldo da conta
        static::deleted(function (Transaction $transaction) {
            if ($transaction->status === 'pago' && $transaction->account) {
                $account = $transaction->account;
                if ($transaction->type === 'receita') {
                    $account->decrement('current_balance', $transaction->amount);
                } elseif ($transaction->type === 'despesa') {
                    $account->increment('current_balance', $transaction->amount);
                }
            }
        });

        // Ao atualizar transação (ex: mudando status para pago, ou alterando valor)
        static::updating(function (Transaction $transaction) {
            $original = $transaction->getOriginal();
            
            // Se houve mudança de valor, status ou conta associada
            if ($original['status'] === 'pago' && $original['account_id']) {
                // Reverter saldo na conta original
                $oldAccount = Account::find($original['account_id']);
                if ($oldAccount) {
                    if ($original['type'] === 'receita') {
                        $oldAccount->decrement('current_balance', $original['amount']);
                    } else {
                        $oldAccount->increment('current_balance', $original['amount']);
                    }
                }
            }
        });

        static::updated(function (Transaction $transaction) {
            // Aplicar novo saldo se a transação atual for paga
            if ($transaction->status === 'pago' && $transaction->account) {
                $account = $transaction->account;
                if ($transaction->type === 'receita') {
                    $account->increment('current_balance', $transaction->amount);
                } elseif ($transaction->type === 'despesa') {
                    $account->decrement('current_balance', $transaction->amount);
                }
            }
        });
    }
}
