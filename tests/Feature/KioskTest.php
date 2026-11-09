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
 * Count single pieces or boxes with a medicine card's +/- buttons, then tap
 * its "Add to basket".
 */
function addToBasket(string $name, Packaging $packaging = Packaging::Piece, int $quantity = 1): TestResponse
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

test('prices are shown in pesos for a single piece and for a box', function () {
    $this->get(route('kiosk', ['q' => 'biogesic']))
        ->assertOk()
        ->assertSeeText('₱5.00 each')
        ->assertSee('₱480.00')
        ->assertSee('Box of 100')
        ->assertSeeText('Save ₱20.00 on 100 single tablets')
        ->assertDontSee('$5.00');
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

test('anyone can add any number of single pieces', function () {
    $biogesic = medicine('Biogesic');

    // One customer wants 11 tablets...
    addToBasket('Biogesic', Packaging::Piece, 11)
        ->assertRedirect(route('kiosk').'#medicine-'.$biogesic->id) // back to the same card
        ->assertSessionHas('success', '11 tablets of Biogesic added to your basket.');

    expect(basketCount())->toBe(11);

    $this->get(route('kiosk'))
        ->assertSee('11 tablets in your basket')
        ->assertViewHas('basketTotal', 55.0);

    // ...and the next one wants 3.
    $this->post(route('kiosk.reset'));
    addToBasket('Biogesic', Packaging::Piece, 3)->assertSessionHas('success', '3 tablets of Biogesic added to your basket.');

    expect(basketCount())->toBe(3);
});

test('a customer can clear the entire basket at once', function () {
    addToBasket('Biogesic', Packaging::Piece, 3);
    addToBasket('Neozep Forte', Packaging::Box);

    $this->from(route('kiosk'))
        ->post(route('basket.clear'), ['panel' => 'basket'])
        ->assertRedirect(route('kiosk'))
        ->assertSessionHas('success', 'Your basket was cleared.');

    expect(basketCount())->toBe(0);
});

test('a box is sold at the box price', function () {
    addToBasket('Biogesic', Packaging::Box)
        ->assertSessionHas('success', '1 box of Biogesic added to your basket.');
    addToBasket('Biogesic', Packaging::Piece);

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['box' => 1, 'piece' => 1]]);

    $this->get(route('kiosk'))
        ->assertSee('1 box and 1 tablet in your basket')
        ->assertViewHas('basketTotal', 485.0);
});

test('pieces and boxes are counted with +/- on the card and added in one tap', function () {
    // Both counters are on the card itself and start at 0. There is no separate pack counter.
    $this->get(route('kiosk', ['q' => 'biogesic']))
        ->assertSee('name="quantities[piece]" value="0"', false)
        ->assertSee('name="quantities[box]" value="0"', false)
        ->assertDontSee('name="quantities[strip]"', false)
        ->assertSee('aria-label="One more tablet of Biogesic"', false)
        ->assertSee('aria-label="One more box of Biogesic"', false);

    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Biogesic')), ['quantities' => ['piece' => 11, 'box' => 2]])
        ->assertSessionHas('success', '11 tablets and 2 boxes of Biogesic added to your basket.');

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['piece' => 11, 'box' => 2]])
        ->and(basketCount())->toBe(13);
});

test('pieces and boxes are checked against the shelf together, all or nothing', function () {
    // Solmux has 400 capsules. Four boxes of 100 would use them all, so the extra capsule doesn't fit.
    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Solmux')), ['quantities' => ['piece' => 1, 'box' => 4]])
        ->assertSessionHasErrors(['quantity' => 'Only 3 boxes of Solmux left in stock.']);

    expect(basketCount())->toBe(0);
});

test('the customer has to choose how many', function (array $payload, string $message) {
    $this->from(route('kiosk'))
        ->post(route('basket.store', medicine('Biogesic')), $payload)
        ->assertRedirectContains(route('kiosk'))
        ->assertSessionHasErrors(['quantities' => $message]);

    expect(basketCount())->toBe(0);
})->with([
    'nothing chosen' => [[], 'Please tap + to choose how many you want.'],
    'zero pieces and zero boxes' => [['quantities' => ['piece' => 0, 'box' => 0]], 'Please tap + to choose how many you want.'],
    'strips, which are no longer sold' => [['quantities' => ['strip' => 1]], 'Please choose single pieces or boxes.'],
]);

test('each medicine says what it is for in plain words, once tapped', function () {
    // The description is in the page but folded away behind "What is it for?".
    $this->get(route('kiosk', ['q' => 'kremil']))
        ->assertSee('What is it for?')
        ->assertSee('aria-controls="uses-shelf-'.medicine('Kremil-S')->id.'"', false)
        ->assertSeeText('An antacid for heartburn, acid indigestion, an upset stomach and gas pain.');

    expect(Medicine::whereNull('description')->count())->toBe(0);
});

test('a guest is sent to the counter for prescription medicine', function () {
    // The guest's card has no counters: the button posts straight away.
    $this->from(route('kiosk'))->post(route('basket.store', medicine('Glucophage')))
        ->assertRedirect()
        ->assertSessionHas('prescription_required.message', MedicinePolicy::PRESCRIPTION_REQUIRED);

    expect(basketCount())->toBe(0);

    // The next page shows the popup with the exact message.
    $this->get(route('kiosk'))->assertSee('Prescription required. Please proceed to the pharmacy counter for assistance.');
});

test('a member can refill a prescription they have bought before', function () {
    signInAs('12345678')->assertSessionHas('success', 'Welcome back, Maria.');

    addToBasket('Glucophage', Packaging::Box)->assertSessionHas('success', 'Prescription on file. 1 box of Glucophage added.');

    // Bought Lipitor 20 mg before, so generic atorvastatin 20 mg is the same prescribed drug.
    addToBasket('RiteMed Atorvastatin')->assertSessionHas('success');

    expect(basketCount())->toBe(2);
});

test('a member cannot add a prescription they have never bought', function () {
    signInAs('12345678');

    addToBasket('Amoxil')->assertSessionHas('prescription_required.name', 'Amoxil');
    addToBasket('RiteMed Co-Amoxiclav')->assertSessionHas('prescription_required');

    expect(basketCount())->toBe(0);
});

test('product matching mode requires the exact product', function () {
    config(['kiosk.prescription_match' => 'product']);

    signInAs('12345678');

    addToBasket('Glucophage')->assertSessionHas('success');
    addToBasket('RiteMed Atorvastatin')->assertSessionHas('prescription_required');
});

test('one member\'s history does not unlock another member\'s prescriptions', function () {
    // Maria (12345678) has filled Glucophage. This member never has.
    User::factory()->create(['member_number' => '87654321']);

    signInAs('87654321');

    addToBasket('Glucophage')->assertSessionHas('prescription_required');
    addToBasket('Biogesic')->assertSessionHas('success');
});

test('the member sign in lists no members and lets a non-member carry on', function () {
    $this->get(route('kiosk'))
        ->assertOk()
        ->assertSeeText('Member card number')
        ->assertSeeText('Not a member?')
        ->assertSeeText('Continue without a card')
        ->assertDontSeeText('Maria Santos')
        ->assertDontSee('12345678');
});

test('signing in from the prescription popup returns to that medicine', function () {
    $glucophage = medicine('Glucophage');

    $this->from(route('kiosk', ['q' => 'gluco']))
        ->post(route('member.store'), ['member_number' => '12345678', 'add' => $glucophage->id])
        ->assertRedirect(route('kiosk', ['q' => 'gluco']).'#medicine-'.$glucophage->id)
        ->assertSessionHas('success', 'Welcome back, Maria. Your prescription for Glucophage is on file. Choose how many you want.');
});

test('signing in for a prescription the member has never bought says why it still cannot be added', function () {
    $this->from(route('kiosk'))
        ->post(route('member.store'), ['member_number' => '12345678', 'add' => medicine('Amoxil')->id])
        ->assertSessionHas('success', 'Welcome back, Maria. We have no record of you buying Amoxil before. Please ask the pharmacist at the counter.');
});

test('a prescription card explains why a guest cannot add it and offers a member sign in', function () {
    $this->get(route('kiosk', ['q' => 'glucophage']))
        ->assertOk()
        ->assertSeeText('Prescription required')
        ->assertSeeText('Filled it with us before? Sign in with your member card to add a refill.')
        ->assertSeeText('Sign in to add a refill')
        ->assertDontSee('Prescription on file');
});

test('a prescription card tells a member when it is not in their purchase history', function () {
    signInAs('12345678');

    $this->get(route('kiosk', ['q' => 'amoxil']))
        ->assertSeeText('We have no record of you buying this before')
        ->assertDontSeeText('Sign in to add a refill');

    $this->get(route('kiosk', ['q' => 'glucophage']))
        ->assertSeeText('Prescription on file')
        ->assertDontSeeText('Prescription required');
});

test('an unknown member card is rejected', function () {
    signInAs('999999')
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors('member_number');

    $this->assertGuest();
});

test('signing out removes prescription refills from the basket', function () {
    signInAs('12345678');
    addToBasket('Glucophage', Packaging::Box);
    addToBasket('Glucophage', Packaging::Piece, 5);
    addToBasket('Kremil-S');

    $this->from(route('kiosk'))->delete(route('member.destroy'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHas('success', 'Signed out. Glucophage left your basket because it needs your prescription history.');

    $this->assertGuest();
    expect(session('kiosk.cart'))->toBe([medicine('Kremil-S')->id => ['piece' => 1]]);
});

test('the basket respects stock levels', function () {
    addToBasket('Advil')->assertSessionHasErrors('medicine_id');

    // Medicol Advance has 40 capsules left.
    addToBasket('Medicol Advance', Packaging::Piece, 40)->assertSessionHas('success');
    addToBasket('Medicol Advance')
        ->assertSessionHasErrors(['quantity' => 'Only 40 capsules of Medicol Advance left in stock.']);

    expect(basketCount())->toBe(40);
});

test('a box needs a full box on the shelf', function () {
    // Ascof Lagundi has 80 tablets left and a box holds 100.
    addToBasket('Ascof Lagundi', Packaging::Box)
        ->assertSessionHasErrors(['quantity' => "There isn't a full box of Ascof Lagundi left. You can buy single tablets instead."]);

    addToBasket('Ascof Lagundi', Packaging::Piece, 80)->assertSessionHas('success');

    expect(basketCount())->toBe(80);
});

test('pieces and boxes of one medicine share its stock', function () {
    medicine('Biogesic')->update(['stock' => 120]);

    addToBasket('Biogesic', Packaging::Box)->assertSessionHas('success');
    addToBasket('Biogesic', Packaging::Piece, 30)
        ->assertSessionHasErrors(['quantity' => 'Only 20 tablets of Biogesic left in stock.']);

    expect(session('kiosk.cart'))->toBe([medicine('Biogesic')->id => ['box' => 1]]);
});

test('there is no fixed limit per medicine unless one is set', function () {
    medicine('Biogesic')->update(['stock' => 10000]);

    addToBasket('Biogesic', Packaging::Piece, 10000)->assertSessionHas('success');

    config(['kiosk.max_quantity_per_item' => 10]);
    $this->post(route('kiosk.reset'));

    addToBasket('Biogesic', Packaging::Piece, 10)->assertSessionHas('success');
    addToBasket('Biogesic')
        ->assertSessionHasErrors(['quantity' => 'You can buy up to 10 tablets of Biogesic per visit.']);

    expect(basketCount())->toBe(10);
});

test('basket quantities can be changed and items removed', function () {
    $biogesic = medicine('Biogesic');
    addToBasket('Biogesic');
    addToBasket('Biogesic', Packaging::Box);

    $this->from(route('kiosk'))->put(route('basket.update', $biogesic), ['packaging' => 'piece', 'quantity' => 3])->assertRedirect(route('kiosk'));
    expect(session('kiosk.cart'))->toBe([$biogesic->id => ['piece' => 3, 'box' => 1]]);

    $this->from(route('kiosk'))->delete(route('basket.destroy', $biogesic), ['packaging' => 'piece'])->assertSessionHas('success', 'Biogesic (tablets) removed.');
    expect(session('kiosk.cart'))->toBe([$biogesic->id => ['box' => 1]]);

    $this->put(route('basket.update', $biogesic), ['packaging' => 'piece', 'quantity' => 1])->assertNotFound();
});

test('checkout creates an order and resets the kiosk', function () {
    signInAs('12345678');
    addToBasket('Glucophage', Packaging::Box, 2);
    addToBasket('Biogesic', Packaging::Piece, 11);
    addToBasket('Biogesic', Packaging::Box);

    $response = $this->post(route('checkout.store'))->assertRedirectContains('/receipt/K');

    $order = Order::with('items')->latest('id')->firstOrFail();

    expect($order->order_number)->toMatch('/^K\d{6}-0001$/')
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->user->name)->toBe('Maria Santos')
        ->and($order->item_count)->toBe(14)
        ->and($order->total)->toBe('2055.00');

    expect($order->items->map(fn ($item) => [$item->name, $item->packaging, $item->quantity, $item->piece_count, $item->line_total])->all())->toBe([
        ['Biogesic', Packaging::Piece, 11, 11, '55.00'],
        ['Biogesic', Packaging::Box, 1, 100, '480.00'],
        ['Glucophage', Packaging::Box, 2, 200, '1520.00'],
    ]);

    // Stock is counted in pieces.
    expect(medicine('Glucophage')->stock)->toBe(400)
        ->and(medicine('Biogesic')->stock)->toBe(1389);

    // The next customer starts from nothing.
    $this->assertGuest();
    expect(basketCount())->toBe(0);

    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('<svg', false)
        ->assertSee('₱2,055.00')
        ->assertSeeText('11 tablets of Biogesic')
        ->assertSeeText('Box of 100 tablets')
        ->assertSeeText('Single tablet')
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
    $this->withSession(['kiosk.cart' => [medicine('Amoxil')->id => ['piece' => 1]]]);

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['basket' => 'Amoxil needs a prescription on file. Please remove it or ask at the counter.']);

    $this->assertDatabaseCount('orders', 3);
});

test('checkout rechecks stock across pieces and boxes', function () {
    addToBasket('Biogesic', Packaging::Box);
    addToBasket('Biogesic', Packaging::Piece);

    // Another kiosk sold most of the shelf in the meantime.
    medicine('Biogesic')->update(['stock' => 10]);

    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertSessionHasErrors(['basket' => 'Sorry, only 10 tablets of Biogesic left. Please update your basket.']);

    $this->assertDatabaseCount('orders', 3);
    expect(medicine('Biogesic')->stock)->toBe(10);
});

test('checkout needs a basket', function () {
    $this->from(route('kiosk'))->post(route('checkout.store'))
        ->assertRedirect(route('kiosk'))
        ->assertSessionHasErrors(['basket' => 'Your basket is empty.']);
});

test('reset signs out and empties the basket', function () {
    signInAs('12345678');
    addToBasket('Glucophage');

    $this->post(route('kiosk.reset'))->assertRedirect(route('welcome'));

    $this->assertGuest();
    expect(basketCount())->toBe(0);
});

test('too many card tries show a message, not an error page', function () {
    foreach (range(1, 10) as $attempt) {
        signInAs('99999'.$attempt);
    }

    signInAs('12345678')
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

    $this->assertDatabaseCount('orders', 3);
});

test('member numbers are unique', function () {
    User::factory()->create(['member_number' => '12345678']);
})->throws(UniqueConstraintViolationException::class);
