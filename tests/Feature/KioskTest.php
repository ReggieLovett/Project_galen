<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Policies\MedicinePolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // The demo catalogue and members define every scenario below.
    $this->seed();
});

/**
 * Tap a member card from the shopping screen.
 */
function signInAs(string $memberNumber): TestResponse
{
    return test()->from(route('kiosk'))->post(route('member.store'), ['member_number' => $memberNumber]);
}

/**
 * Tap "Add to basket" on a medicine from the shopping screen.
 */
function addToBasket(string $name, int $quantity = 1): TestResponse
{
    return test()->from(route('kiosk'))->post(route('basket.store', medicine($name)), ['quantity' => $quantity]);
}

test('the idle screen starts a fresh session', function () {
    $this->get(route('welcome'))->assertOk()->assertSee('Touch to begin');

    $this->post(route('kiosk.start'))->assertRedirect(route('kiosk'));

    $this->get(route('kiosk'))
        ->assertOk()
        ->assertSee('Advil Pain Reliever')
        ->assertSee(route('basket.store', medicine('Motrin IB')), false); // a real form, not a script
});

test('search and filters are query strings', function () {
    $this->get(route('kiosk', ['q' => 'ibuprofen']))
        ->assertOk()
        ->assertSee('3 medicines for “ibuprofen”', false)
        ->assertViewHas('medicines', fn ($medicines) => $medicines->pluck('name')->all() === ['Advil Pain Reliever', 'Motrin IB', 'Nurofen Express']);

    $this->get(route('kiosk', ['type' => 'prescription']))
        ->assertViewHas('medicines', fn ($medicines) => $medicines->count() === 10);

    $this->get(route('kiosk', ['type' => 'otc', 'class' => 'Allergy']))
        ->assertViewHas('medicines', fn ($medicines) => $medicines->count() === 6);

    $this->get(route('kiosk', ['type' => 'vitamins']))->assertSessionHasErrors('type');
});

test('anyone can add over-the-counter medicine', function () {
    $motrin = medicine('Motrin IB');

    addToBasket('Motrin IB', 2)
        ->assertRedirect(route('kiosk').'#medicine-'.$motrin->id) // back to the same card
        ->assertSessionHas('success', 'Motrin IB added to your basket.');

    expect(basketCount())->toBe(2);

    $this->get(route('kiosk'))
        ->assertSee('2 in your basket')
        ->assertViewHas('basketTotal', 15.98);
});

test('a guest is sent to the counter for prescription medicine', function () {
    addToBasket('Glucophage')
        ->assertRedirect()
        ->assertSessionHas('prescription_required.message', MedicinePolicy::PRESCRIPTION_REQUIRED);

    expect(basketCount())->toBe(0);

    // The next page shows the popup with the exact message.
    $this->get(route('kiosk'))->assertSee('Prescription required. Please proceed to the pharmacy counter for assistance.');
});

test('a member can refill a prescription they have bought before', function () {
    signInAs('100245')->assertSessionHas('success', 'Welcome back, Maria.');

    addToBasket('Glucophage')->assertSessionHas('success', 'Prescription on file. Glucophage added.');

    // Bought Lipitor 20 mg before, so generic atorvastatin 20 mg is the same prescribed drug.
    addToBasket('Atorvastatin Calcium')->assertSessionHas('success');

    expect(basketCount())->toBe(2);
});

test('a member cannot add a prescription they have never bought', function () {
    signInAs('100245');

    addToBasket('Amoxil')->assertSessionHas('prescription_required.name', 'Amoxil');
    addToBasket('Azithromycin')->assertSessionHas('prescription_required');

    expect(basketCount())->toBe(0);
});

test('product matching mode requires the exact product', function () {
    config(['kiosk.prescription_match' => 'product']);

    signInAs('100245');

    addToBasket('Glucophage')->assertSessionHas('success');
    addToBasket('Atorvastatin Calcium')->assertSessionHas('prescription_required');
});

test('one member\'s history does not unlock another member\'s prescriptions', function () {
    signInAs('100318');

    addToBasket('Ventolin HFA')->assertSessionHas('success');
    addToBasket('Glucophage')->assertSessionHas('prescription_required');
});

test('signing in from the prescription popup returns to that medicine', function () {
    $glucophage = medicine('Glucophage');

    $this->from(route('kiosk', ['q' => 'gluco']))
        ->post(route('member.store'), ['member_number' => '100245', 'add' => $glucophage->id])
        ->assertRedirect(route('kiosk', ['q' => 'gluco']).'#medicine-'.$glucophage->id)
        ->assertSessionHas('success', 'Welcome back, Maria. Tap Add to basket on Glucophage again.');
});

test('an unknown member card is rejected', function () {
    signInAs('999999')
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors('member_number');

    $this->assertGuest();
});

test('signing out removes prescription refills from the basket', function () {
    signInAs('100245');
    addToBasket('Glucophage');
    addToBasket('Tums Extra Strength');

    $this->from(route('kiosk'))->delete(route('member.destroy'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHas('success', 'Signed out. Glucophage left your basket because it needs your prescription history.');

    $this->assertGuest();
    expect(session('kiosk.cart'))->toBe([medicine('Tums Extra Strength')->id => 1]);
});

test('the basket respects stock levels', function () {
    addToBasket('Advil Pain Reliever')->assertSessionHasErrors('medicine_id');

    // Aleve has 4 left.
    addToBasket('Aleve All Day Strong', 4)->assertSessionHas('success');
    addToBasket('Aleve All Day Strong')
        ->assertSessionHasErrors(['quantity' => 'Only 4 of Aleve All Day Strong left in stock.']);

    expect(basketCount())->toBe(4);
});

test('basket quantities can be changed and items removed', function () {
    $motrin = medicine('Motrin IB');
    addToBasket('Motrin IB');

    $this->from(route('kiosk'))->put(route('basket.update', $motrin), ['quantity' => 3])->assertRedirect(route('kiosk'));
    expect(basketCount())->toBe(3);

    $this->from(route('kiosk'))->delete(route('basket.destroy', $motrin))->assertSessionHas('success', 'Motrin IB removed.');
    expect(basketCount())->toBe(0);

    $this->put(route('basket.update', $motrin), ['quantity' => 1])->assertNotFound();
});

test('checkout creates an order and resets the kiosk', function () {
    signInAs('100245');
    addToBasket('Glucophage', 2);
    addToBasket('Panadol Advance');

    $response = $this->post(route('checkout.store'))->assertRedirectContains('/receipt/K');

    $order = Order::latest('id')->firstOrFail();

    expect($order->order_number)->toMatch('/^K\d{6}-0001$/')
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->user->name)->toBe('Maria Santos')
        ->and($order->total)->toBe('37.49')
        ->and(medicine('Glucophage')->stock)->toBe(48);

    // The next customer starts from nothing.
    $this->assertGuest();
    expect(basketCount())->toBe(0);

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('<svg', false)
        ->assertSee('Contains prescription items');
});

test('receipts need the signed link', function () {
    addToBasket('Tums Extra Strength');
    $this->post(route('checkout.store'));
    $number = Order::latest('id')->value('order_number');

    $this->get(route('receipt', $number))->assertForbidden();
    $this->get(URL::temporarySignedRoute('receipt', now()->addMinute(), $number))->assertOk();
});

test('checkout rechecks prescriptions', function () {
    // A prescription item that slipped into a guest's basket is still refused.
    $this->withSession(['kiosk.cart' => [medicine('Amoxil')->id => 1]]);

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['basket' => 'Amoxil needs a prescription on file. Please remove it or ask at the counter.']);

    $this->assertDatabaseCount('orders', 4);
});

test('checkout needs a basket', function () {
    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['basket' => 'Your basket is empty.']);
});

test('reset signs out and empties the basket', function () {
    signInAs('100245');
    addToBasket('Glucophage');

    $this->post(route('kiosk.reset'))->assertRedirect(route('welcome'));

    $this->assertGuest();
    expect(basketCount())->toBe(0);
});

test('too many card tries show a message, not an error page', function () {
    foreach (range(1, 10) as $attempt) {
        signInAs('99999'.$attempt);
    }

    signInAs('100245')
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['member_number' => 'Too many tries. Please wait a minute or ask at the counter.']);

    $this->assertGuest();
});

test('checkout waits its turn when another kiosk is checking out', function () {
    addToBasket('Tums Extra Strength');

    // Another kiosk holds the checkout lock, so the order number and stock check wait.
    expect(Cache::lock('kiosk:checkout', 10)->get())->toBeTrue();

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertSessionHasErrors(['basket' => 'The till is busy. Please tap Place order again.']);

    $this->assertDatabaseCount('orders', 4);
});

test('member numbers are unique', function () {
    User::factory()->create(['member_number' => '100245']);
})->throws(UniqueConstraintViolationException::class);
