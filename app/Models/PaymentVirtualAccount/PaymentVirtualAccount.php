<?php

namespace App\Models\PaymentVirtualAccount;

use App\Models\Order\Order;
use App\Models\Transaction\Transaction;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVirtualAccount extends Model
{
    protected $table = 'payment_virtual_accounts';

    protected $fillable = [
        'user_id',
        'order_id',
        'transaction_id',
        'virtual_account_no',
        'virtual_account_name',
        'virtual_account_expiry',
        'amount',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'virtual_account_expiry' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
