<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'user_id', 'channel', 'status', 'item_count', 'total', 'completed_at'])]
class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', OrderStatus::Completed);
    }

    #[Scope]
    protected function awaitingPayment(Builder $query): void
    {
        $query->where('status', OrderStatus::AwaitingPayment);
    }

    /**
     * Next kiosk order number for today, e.g. "K261103-0007". Only call it
     * while holding CheckoutController's lock: two checkouts counting at the
     * same moment would get the same number.
     */
    public static function nextKioskNumber(): string
    {
        $prefix = 'K'.now()->format('ymd');

        $issuedToday = static::query()->whereLike('order_number', "{$prefix}-%")->count();

        return sprintf('%s-%04d', $prefix, $issuedToday + 1);
    }

    /**
     * The short number the cashier calls out ("0007").
     */
    public function queueNumber(): string
    {
        return str($this->order_number)->afterLast('-')->toString();
    }

    /**
     * Does the pharmacist need to check anything at handover? Load
     * items.medicine.category first.
     */
    public function hasPrescriptionItems(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->medicine->requiresPrescription());
    }
}
