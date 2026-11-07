<?php

use App\Enums\OrderStatus;
use App\Enums\Packaging;
use App\Models\Medicine;
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
 * Tap "Add to basket" on a medicine, count strips or boxes with the +/-
 * buttons, then tap "Add to basket" in the chooser.
 */
function addToBasket(string $name, Packaging $packaging = Packaging::Strip, int $quantity = 1): TestResponse
{
    return test()->from(route('kiosk'))->post(route('basket.store', medicine($name)), [
        'quantities' => [$packaging->value => $quantity],
    ]);
}

test('the idle screen starts a fresh session', function () {
    $this->get(route('welcome'))->assertOk()->assertSee('Touch to begin');

    $this->post(route('kiosk.start'))->assertRedirect(route('kiosk'));

    $this->get(route('kiosk'))
        ->assertOk()
        ->assertSee('Neozep Forte')
        ->assertSee(route('basket.store', medicine('Biogesic')), false); // a real form, not a script
});

test('prices are shown in pesos for a strip and for a box', function () {
    $this->get(route('kiosk', ['q' => 'biogesic']))
        ->assertOk()
        ->assertSee('₱50.00')
        ->assertSee('₱480.00')
        ->assertSee('Box of 10 strips (100 tablets)')
        ->assertSeeText('Save ₱20.00')
        ->assertDontSee('$50.00');
});

test('search and filters are query strings', function () {
    $this->get(route('kiosk', ['q' => 'ibuprofen']))
        ->assertOk()
        ->assertSee('4 medicines for “ibuprofen”', false)
        ->assertViewHas('medicines', fn ($medicines) => $medicines->pluck('name')->all() === ['Advil', 'Alaxan FR', 'Medicol Advance', 'RiteMed Ibuprofen']);

    $this->get(route('kiosk', ['type' => 'prescription']))
        ->assertViewHas('medicines', fn ($medicines) => $medicines->count() === 12);

    $this->get(route('kiosk', ['type' => 'otc', 'class' => 'Allergy']))
        ->assertViewHas('medicines', fn ($medicines) => $medicines->count() === 4);

    $this->get(route('kiosk', ['type' => 'vitamins']))->assertSessionHasErrors('type');
});

test('anyone can add over-the-counter medicine by the strip', function () {
    $biogesic = medicine('Biogesic');

    addToBasket('Biogesic', Packaging::Strip, 2)
        ->assertRedirect(route('kiosk').'#medicine-'.$biogesic->id) // back to the same card
        ->assertSessionHas('success', '2 strips of Biogesic added to your basket.');

    expect(basketCount())->toBe(2);

    $this->get(route('kiosk'))
        ->assertSee('2 strips in your basket')
        ->assertViewHas('basketTotal', 100.0);
});

test('a box is sold at the box price', function () {
    addToBasket('Biogesic', Packaging::Box)
        ->assertSessionHas('success', '1 box of Biogesic added to your basket.');
    addToBasket('Biogesic', Packaging::Strip);

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['box' => 1, 'strip' => 1]]);

    $this->get(route('kiosk'))
        ->assertSee('1 box and 1 strip in your basket')
        ->assertViewHas('basketTotal', 530.0);
});

test('strips and boxes are counted with +/- and added in one tap', function () {
    $this->get(route('kiosk', ['q' => 'biogesic']))
        ->assertSee('How many would you like?')
        ->assertSee('name="quantities[strip]"', false)
        ->assertSee('name="quantities[box]"', false)
        ->assertSee('aria-label="One more box"', false);

    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Biogesic')), ['quantities' => ['strip' => 3, 'box' => 2]])
        ->assertSessionHas('success', '3 strips and 2 boxes of Biogesic added to your basket.');

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['strip' => 3, 'box' => 2]])
        ->and(basketCount())->toBe(5);
});

test('strips and boxes are checked against the shelf together, all or nothing', function () {
    // Solmux has 40 strips. Four boxes would use them all, so the extra strip doesn't fit.
    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Solmux')), ['quantities' => ['strip' => 1, 'box' => 4]])
        ->assertSessionHasErrors(['quantity' => 'Only 3 boxes of Solmux left in stock.']);

    expect(basketCount())->toBe(0);
});

test('the customer has to choose how many strips or boxes', function (array $payload, string $message) {
    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Biogesic')), $payload)
        ->assertRedirectContains(route('kiosk'))
        ->assertSessionHasErrors(['quantities' => $message]);

    expect(basketCount())->toBe(0);
})->with([
    'nothing chosen' => [[], 'Please choose how many strips or boxes you want.'],
    'zero strips and zero boxes' => [['quantities' => ['strip' => 0, 'box' => 0]], 'Please choose how many strips or boxes you want.'],
    'an unknown packaging' => [['quantities' => ['bottle' => 1]], 'Please choose strips or boxes.'],
]);

test('each medicine says what it is for in plain words', function () {
    $this->get(route('kiosk', ['q' => 'kremil']))
        ->assertSeeText('An antacid for heartburn, acid indigestion, an upset stomach and gas pain.');

    expect(Medicine::whereNull('description')->count())->toBe(0);
});

test('a guest is sent to the counter for prescription medicine', function () {
    // The guest's card has no strip-or-box step: the button posts straight away.
    $this->from(route('kiosk'))->post(route('basket.store', medicine('Glucophage')))
        ->assertRedirect()
        ->assertSessionHas('prescription_required.message', MedicinePolicy::PRESCRIPTION_REQUIRED);

    expect(basketCount())->toBe(0);

    // The next page shows the popup with the exact message.
    $this->get(route('kiosk'))->assertSee('Prescription required. Please proceed to the pharmacy counter for assistance.');
});

test('a member can refill a prescription they have bought before', function () {
    signInAs('100245')->assertSessionHas('success', 'Welcome back, Maria.');

    addToBasket('Glucophage', Packaging::Box)->assertSessionHas('success', 'Prescription on file. 1 box of Glucophage added.');

    // Bought Lipitor 20 mg before, so generic atorvastatin 20 mg is the same prescribed drug.
    addToBasket('RiteMed Atorvastatin')->assertSessionHas('success');

    expect(basketCount())->toBe(2);
});

test('a member cannot add a prescription they have never bought', function () {
    signInAs('100245');

    addToBasket('Amoxil')->assertSessionHas('prescription_required.name', 'Amoxil');
    addToBasket('RiteMed Co-Amoxiclav')->assertSessionHas('prescription_required');

    expect(basketCount())->toBe(0);
});

test('product matching mode requires the exact product', function () {
    config(['kiosk.prescription_match' => 'product']);

    signInAs('100245');

    addToBasket('Glucophage')->assertSessionHas('success');
    addToBasket('RiteMed Atorvastatin')->assertSessionHas('prescription_required');
});

test('one member\'s history does not unlock another member\'s prescriptions', function () {
    signInAs('100318');

    addToBasket('Cozaar')->assertSessionHas('success');
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
    addToBasket('Glucophage', Packaging::Box);
    addToBasket('Glucophage', Packaging::Strip);
    addToBasket('Kremil-S');

    $this->from(route('kiosk'))->delete(route('member.destroy'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHas('success', 'Signed out. Glucophage left your basket because it needs your prescription history.');

    $this->assertGuest();
    expect(session('kiosk.cart'))->toBe([medicine('Kremil-S')->id => ['strip' => 1]]);
});

test('the basket respects stock levels', function () {
    addToBasket('Advil')->assertSessionHasErrors('medicine_id');

    // Medicol Advance has 4 strips left.
    addToBasket('Medicol Advance', Packaging::Strip, 4)->assertSessionHas('success');
    addToBasket('Medicol Advance')
        ->assertSessionHasErrors(['quantity' => 'Only 4 strips of Medicol Advance left in stock.']);

    expect(basketCount())->toBe(4);
});

test('a box needs a full box of strips on the shelf', function () {
    // Ascof Lagundi has 8 strips left and a box holds 10.
    addToBasket('Ascof Lagundi', Packaging::Box)
        ->assertSessionHasErrors(['quantity' => "There isn't a full box of Ascof Lagundi left. You can buy it by the strip."]);

    addToBasket('Ascof Lagundi', Packaging::Strip, 8)->assertSessionHas('success');

    expect(basketCount())->toBe(8);
});

test('strips and boxes of one medicine share its stock', function () {
    medicine('Biogesic')->update(['stock' => 12]);

    addToBasket('Biogesic', Packaging::Box)->assertSessionHas('success');
    addToBasket('Biogesic', Packaging::Strip, 3)
        ->assertSessionHasErrors(['quantity' => 'Only 2 strips of Biogesic left in stock.']);

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['box' => 1]]);
});

test('each strip or box line is capped per visit', function () {
    addToBasket('Biogesic', Packaging::Strip, 10)->assertSessionHas('success');
    addToBasket('Biogesic')
        ->assertSessionHasErrors(['quantity' => 'You can buy up to 10 strips of Biogesic per visit.']);

    expect(basketCount())->toBe(10);
});

test('basket quantities can be changed and items removed', function () {
    $biogesic = medicine('Biogesic');
    addToBasket('Biogesic');
    addToBasket('Biogesic', Packaging::Box);

    $this->from(route('kiosk'))->put(route('basket.update', $biogesic), ['packaging' => 'strip', 'quantity' => 3])->assertRedirect(route('kiosk'));
    expect(session('kiosk.cart'))->toBe([$biogesic->id => ['strip' => 3, 'box' => 1]]);

    $this->from(route('kiosk'))->delete(route('basket.destroy', $biogesic), ['packaging' => 'strip'])->assertSessionHas('success', 'Biogesic (strip) removed.');
    expect(session('kiosk.cart'))->toBe([$biogesic->id => ['box' => 1]]);

    $this->put(route('basket.update', $biogesic), ['packaging' => 'strip', 'quantity' => 1])->assertNotFound();
});

test('checkout creates an order and resets the kiosk', function () {
    signInAs('100245');
    addToBasket('Glucophage', Packaging::Box, 2);
    addToBasket('Biogesic', Packaging::Strip);
    addToBasket('Biogesic', Packaging::Box);

    $response = $this->post(route('checkout.store'))->assertRedirectContains('/receipt/K');

    $order = Order::with('items')->latest('id')->firstOrFail();

    expect($order->order_number)->toMatch('/^K\d{6}-0001$/')
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->user->name)->toBe('Maria Santos')
        ->and($order->item_count)->toBe(4)
        ->and($order->total)->toBe('2050.00');

    expect($order->items->map(fn ($item) => [$item->name, $item->packaging, $item->quantity, $item->strip_count, $item->line_total])->all())->toBe([
        ['Biogesic', Packaging::Strip, 1, 1, '50.00'],
        ['Biogesic', Packaging::Box, 1, 10, '480.00'],
        ['Glucophage', Packaging::Box, 2, 20, '1520.00'],
    ]);

    // Stock is counted in strips.
    expect(medicine('Glucophage')->stock)->toBe(40)
        ->and(medicine('Biogesic')->stock)->toBe(139);

    // The next customer starts from nothing.
    $this->assertGuest();
    expect(basketCount())->toBe(0);

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('<svg', false)
        ->assertSee('₱2,050.00')
        ->assertSeeText('₱480.00 per box')
        ->assertSee('Contains prescription items');
});

test('receipts need the signed link', function () {
    addToBasket('Kremil-S');
    $this->post(route('checkout.store'));
    $number = Order::latest('id')->value('order_number');

    $this->get(route('receipt', $number))->assertForbidden();
    $this->get(URL::temporarySignedRoute('receipt', now()->addMinute(), $number))->assertOk();
});

test('checkout rechecks prescriptions', function () {
    // A prescription item that slipped into a guest's basket is still refused.
    $this->withSession(['kiosk.cart' => [medicine('Amoxil')->id => ['strip' => 1]]]);

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['basket' => 'Amoxil needs a prescription on file. Please remove it or ask at the counter.']);

    $this->assertDatabaseCount('orders', 4);
});

test('checkout rechecks stock across strips and boxes', function () {
    addToBasket('Biogesic', Packaging::Box);
    addToBasket('Biogesic', Packaging::Strip);

    // Another kiosk sold most of the shelf in the meantime.
    medicine('Biogesic')->update(['stock' => 10]);

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertSessionHasErrors(['basket' => 'Sorry, only 10 strips of Biogesic left. Please update your basket.']);

    $this->assertDatabaseCount('orders', 4);
    expect(medicine('Biogesic')->stock)->toBe(10);
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
    addToBasket('Kremil-S');

    // Another kiosk holds the checkout lock, so the order number and stock check wait.
    expect(Cache::lock('kiosk:checkout', 10)->get())->toBeTrue();

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertSessionHasErrors(['basket' => 'The till is busy. Please tap Place order again.']);

    $this->assertDatabaseCount('orders', 4);
});

test('member numbers are unique', function () {
    User::factory()->create(['member_number' => '100245']);
})->throws(UniqueConstraintViolationException::class);
