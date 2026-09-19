<?php

namespace App\Models\Order;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Transaction\Transaction;
use App\Models\User\User;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $package_id
 * @property string $order_number
 * @property float $total_price
 * @property OrderStatus $status
 * @property OrderPaymentStatus $payment_status
 * @property \Illuminate\Support\Carbon $booking_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $event_date
 * @property-read Transaction|null $latestTransaction
 * @property-read Package $package
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBookingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalPrice($value)
 * @method static \App\Models\Order\Order|null find(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Order\Order findOrFail(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Order\Order|null first(array|string $columns = ['*'])
 * @method static \App\Models\Order\Order firstOrFail(array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order\Order> get(array|string $columns = ['*'])
 *
 * @property int $userId
 * @property int $packageId
 * @property string $orderNumber
 * @property numeric $totalPrice
 * @property OrderStatus $status
 * @property OrderPaymentStatus $paymentStatus
 * @property \Illuminate\Support\Carbon $bookingDate
 * @property \Illuminate\Support\Carbon|null $createdAt
 * @property \Illuminate\Support\Carbon|null $updatedAt
 * @property-read mixed $eventDate
 * @property-read int|null $transactionsCount
 * @property-read bool|null $transactionsExists
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Order\Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Order\Order whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Order extends Model
{
    use LegacyMorphClass;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'product_id',
        'order_number',
        'total_price',
        'status',
        'payment_status',
        'booking_date',
        'booking_time',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_price' => 'decimal:2',
        'status' => OrderStatus::class,
        'payment_status' => OrderPaymentStatus::class,
    ];

    protected $appends = ['event_date'];

    public function getEventDateAttribute()
    {
        return Carbon::parse($this->booking_date)->format('Y-m-d');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function latestTransaction()
    {
        return $this->hasOne(Transaction::class)->latestOfMany();
    }
}
