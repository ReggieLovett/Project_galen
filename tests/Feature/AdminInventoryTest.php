<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed();
});

function inventoryStaff(): Admin
{
    return Admin::create([
        'name' => 'Inventory Manager',
        'email' => 'inventory@example.test',
        'password' => 'correct-horse-battery',
    ]);
}

test('inventory is protected from unauthenticated users', function () {
    $this->get(route('admin.medicines.index'))
        ->assertRedirect(route('admin.login'));
});

test('staff can list medicines and edit stock', function () {
    $medicine = Medicine::where('name', 'Advil')->firstOrFail();
    $staff = inventoryStaff();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.medicines.index', ['search' => 'Advil']))
        ->assertOk()
        ->assertSee($medicine->fullName())
        ->assertSee('Out of stock')
        ->assertSee('Restock needed');

    $this->actingAs($staff, 'admin')
        ->put(route('admin.medicines.update', $medicine), [
            'category_id' => $medicine->category_id,
            'name' => $medicine->name,
            'brand' => $medicine->brand,
            'active_ingredient' => $medicine->active_ingredient,
            'strength' => $medicine->strength,
            'dosage_form' => $medicine->dosage_form,
            'pieces_per_strip' => $medicine->pieces_per_strip,
            'strips_per_box' => $medicine->strips_per_box,
            'therapeutic_class' => $medicine->therapeutic_class,
            'description' => $medicine->description,
            'piece_price' => $medicine->piece_price,
            'box_price' => $medicine->box_price,
            'image_path' => $medicine->image_path,
            'stock' => 80,
        ])
        ->assertRedirect(route('admin.medicines.index'));

    expect($medicine->fresh()->stock)->toBe(80);

    $this->get(route('admin.medicines.index', ['search' => 'Advil']))
        ->assertOk()
        ->assertSee('In stock')
        ->assertDontSee('Restock needed');

    Auth::shouldUse('web');

    $this->get(route('kiosk'))
        ->assertOk()
        ->assertSee('In stock');
});

test('staff can search medicines by name, brand, ingredient, or class', function () {
    $staff = inventoryStaff();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.medicines.index', ['search' => 'Advil']))
        ->assertOk()
        ->assertSee('Advil')
        ->assertSee('matching your search');

    $this->actingAs($staff, 'admin')
        ->get(route('admin.medicines.index', ['search' => 'does-not-exist']))
        ->assertOk()
        ->assertSee('No matching medicines')
        ->assertSee('Try a different name');
});

test('inventory updates validate stock values', function () {
    $medicine = Medicine::firstOrFail();

    $this->actingAs(inventoryStaff(), 'admin')
        ->from(route('admin.medicines.edit', $medicine))
        ->put(route('admin.medicines.update', $medicine), ['stock' => -1])
        ->assertRedirect(route('admin.medicines.edit', $medicine))
        ->assertSessionHasErrors(['stock']);
});

test('staff can add and delete an unreferenced medicine', function () {
    $category = Category::where('slug', 'otc')->firstOrFail();
    $staff = inventoryStaff();

    $this->actingAs($staff, 'admin')
        ->post(route('admin.medicines.store'), [
            'category_id' => $category->id,
            'name' => 'Test Relief',
            'brand' => 'Galen',
            'active_ingredient' => 'Paracetamol',
            'strength' => '500 mg',
            'dosage_form' => 'Tablet',
            'pieces_per_strip' => 10,
            'strips_per_box' => 10,
            'therapeutic_class' => 'Pain & Fever',
            'description' => 'For test use.',
            'piece_price' => 2,
            'box_price' => 190,
            'image_path' => null,
            'stock' => 100,
        ])
        ->assertRedirect(route('admin.medicines.index'));

    $medicine = Medicine::where('name', 'Test Relief')->firstOrFail();

    $this->actingAs($staff, 'admin')
        ->delete(route('admin.medicines.destroy', $medicine))
        ->assertRedirect(route('admin.medicines.index'));

    expect(Medicine::find($medicine->id))->toBeNull();
});

test('every inventory page logs idle staff out and has the staff header', function () {
    $medicine = Medicine::where('name', 'Advil')->firstOrFail();

    $this->actingAs(inventoryStaff(), 'admin');

    foreach ([route('admin.medicines.index'), route('admin.medicines.create'), route('admin.medicines.edit', $medicine)] as $page) {
        $this->get($page)
            ->assertOk()
            ->assertSeeText('Are you still there?')
            ->assertSeeText('Log out')
            ->assertSee(route('admin.dashboard'), false);
    }
});

test('dashboard stock alerts open the medicine at its stock field', function () {
    $outOfStock = Medicine::where('name', 'Advil')->firstOrFail();

    $this->actingAs(inventoryStaff(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.medicines.edit', $outOfStock).'#stock', false)
        ->assertSee(route('admin.medicines.index'), false);
});
