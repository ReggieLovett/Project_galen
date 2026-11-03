<?php

use App\Models\Admin;

beforeEach(function () {
    // The demo members are needed to show a card can't open the staff area.
    $this->seed();
});

/**
 * A staff account with a known password.
 */
function staffMember(): Admin
{
    return Admin::create([
        'name' => 'Priya Nair',
        'email' => 'priya@riverside.test',
        'password' => 'correct-horse-battery',
    ]);
}

test('kiosk screens do not link to the staff area', function () {
    $this->post(route('kiosk.start'));

    $this->get(route('kiosk'))
        ->assertOk()
        ->assertDontSee(config('kiosk.admin.path'))
        ->assertDontSee('Staff', false);
});

test('the staff login page is hidden from search engines', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Staff login')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    expect(parse_url(route('admin.login'), PHP_URL_PATH))->toBe('/staff-portal/login');
});

test('there is no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/'.config('kiosk.admin.path').'/register')->assertNotFound();
});

test('the dashboard requires a staff login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('a member card does not open the staff area', function () {
    $this->post(route('member.store'), ['member_number' => '100245'])->assertRedirect();

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('staff can log in and see the dashboard', function () {
    $staff = staffMember();

    $this->post(route('admin.login.store'), ['email' => $staff->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($staff, 'admin');
    $this->assertGuest('web');
    expect($staff->fresh()->last_login_at)->not->toBeNull();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Signed in as Priya Nair')
        ->assertSee('Advil Pain Reliever'); // out of stock alert

    // Already signed in: the login page sends staff on to the dashboard.
    $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
});

test('a wrong password is rejected', function () {
    $staff = staffMember();

    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), ['email' => $staff->email, 'password' => 'not-it'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionHasInput('email', $staff->email)
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest('admin');
});

test('login attempts are rate limited', function () {
    $staff = staffMember();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.login.store'), ['email' => $staff->email, 'password' => 'guess-'.$attempt]);
    }

    // Even the right password waits, and the page says so instead of erroring.
    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), ['email' => $staff->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    $this->assertGuest('admin');
});

test('staff can log out', function () {
    $this->actingAs(staffMember(), 'admin')
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'))
        ->assertSessionHas('success');

    $this->assertGuest('admin');
});

test('starting a kiosk session signs staff out on a shared device', function () {
    $this->actingAs(staffMember(), 'admin');

    $this->post(route('kiosk.start'));

    $this->assertGuest('admin');
});

test('the create-admin command creates and validates accounts', function () {
    $this->artisan('kiosk:admin', ['email' => 'new@riverside.test', '--name' => 'New Admin'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-enough-password')
        ->expectsOutput('Created staff admin new@riverside.test.')
        ->assertSuccessful();

    $this->artisan('kiosk:admin', ['email' => 'new@riverside.test'])
        ->expectsQuestion('Password (at least 12 characters)', 'short')
        ->assertFailed();

    $this->assertDatabaseHas('admins', ['email' => 'new@riverside.test', 'name' => 'New Admin']);
});
