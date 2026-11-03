<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    // The demo catalogue and members are used to build kiosk orders.
    $this->seed();
});

/**
 * Sign in a staff member for the rest of the test.
 */
function actingAsStaff(): TestCase
{
    return test()->actingAs(Admin::create([
        'name' => 'Priya Nair',
        'email' => 'priya@riverside.test',
        'password' => 'correct-horse-battery',
    ]), 'admin');
}

/**
 * A kiosk order as checkout leaves it: awaiting payment, stock reserved.
 *
 * @param  array<string, int>  $lines  medicine name => quantity
 */
function kioskOrder(array $lines, ?User $customer = null): Order
{
    $order = Order::create([
        'order_number' => Order::nextKioskNumber(),
        'user_id' => $customer?->id,
        'channel' => OrderChannel::Kiosk,
        'status' => OrderStatus::AwaitingPayment,
        'item_count' => array_sum($lines),
        'total' => 0,
    ]);

    foreach ($lines as $name => $quantity) {
        $medicine = medicine($name);

        $order->items()->create([
            'medicine_id' => $medicine->id,
            'name' => $medicine->name,
            'brand' => $medicine->brand,
            'strength' => $medicine->strength,
            'unit_price' => $medicine->price,
            'quantity' => $quantity,
            'line_total' => $medicine->price * $quantity,
        ]);

        $medicine->decrement('stock', $quantity);
    }

    return $order;
}

test('the dashboard lists what to fetch for each order', function () {
    $order = kioskOrder(['Amoxil' => 1, 'Tums Extra Strength' => 2]);

    actingAsStaff()
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('1 × Amoxil, 2 × Tums Extra Strength')
        ->assertSee('Prescription · pharmacist check')
        ->assertSee('Mark paid');
});

test('marking an order paid turns it into purchase history', function () {
    $maria = User::where('member_number', '100245')->firstOrFail();
    $order = kioskOrder(['Amoxil' => 1], $maria);

    // Unpaid, it doesn't count yet.
    expect($maria->hasPurchasedBefore(medicine('Amoxil')))->toBeFalse();

    actingAsStaff()
        ->from(route('admin.dashboard'))
        ->patch(route('admin.orders.complete', $order))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success', "Order {$order->order_number} marked as paid.");

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull()
        ->and($maria->hasPurchasedBefore(medicine('Amoxil')))->toBeTrue()
        // Paying doesn't touch stock: checkout already took it off the shelf.
        ->and(medicine('Amoxil')->stock)->toBe(24);
});

test('cancelling an order puts the stock back', function () {
    $order = kioskOrder(['Motrin IB' => 3, 'Aleve All Day Strong' => 4]);

    expect(medicine('Motrin IB')->stock)->toBe(15);

    actingAsStaff()
        ->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(medicine('Motrin IB')->stock)->toBe(18)
        ->and(medicine('Aleve All Day Strong')->stock)->toBe(4);
});

test('an order can only be handled once', function () {
    $order = kioskOrder(['Motrin IB' => 1]);

    actingAsStaff()->patch(route('admin.orders.cancel', $order));

    // A second till tries to cancel or charge the same slip.
    $this->patch(route('admin.orders.cancel', $order))
        ->assertSessionHas('error', "Order {$order->order_number} was already cancelled.");
    $this->patch(route('admin.orders.complete', $order))
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(medicine('Motrin IB')->stock)->toBe(18); // restored once, not twice
});

test('past counter sales cannot be cancelled', function () {
    $sale = Order::where('channel', OrderChannel::Counter)->firstOrFail();

    actingAsStaff()
        ->patch(route('admin.orders.cancel', $sale))
        ->assertSessionHas('error', "Order {$sale->order_number} was already completed.");

    expect($sale->refresh()->status)->toBe(OrderStatus::Completed);
});

test('only staff can handle orders', function () {
    $order = kioskOrder(['Motrin IB' => 1]);

    $this->patch(route('admin.orders.complete', $order))->assertRedirect(route('admin.login'));

    // A signed-in member is not staff.
    $this->post(route('member.store'), ['member_number' => '100245']);
    $this->patch(route('admin.orders.cancel', $order))->assertRedirect(route('admin.login'));

    expect($order->refresh()->status)->toBe(OrderStatus::AwaitingPayment);
});
