<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrderStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['member_number', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasManyThrough<OrderItem, Order, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    /**
     * Has this customer completed a purchase of this drug before? What
     * counts as "this drug" is set by kiosk.prescription_match.
     */
    public function hasPurchasedBefore(Medicine $medicine): bool
    {
        return $this->orderItems()
            ->where('orders.status', OrderStatus::Completed)
            ->whereHas('medicine', fn (Builder $query) => config('kiosk.prescription_match') === 'product'
                ? $query->whereKey($medicine->getKey())
                : $query->sameIngredientAs($medicine)->where('strength', $medicine->strength))
            ->exists();
    }

    public function firstName(): string
    {
        return str($this->name)->before(' ')->toString();
    }
}
