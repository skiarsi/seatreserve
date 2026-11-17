<?php

use App\Enums\SeatSection;
use App\Models\Hall;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

it('seeds admin@example.com with password and default hall layout', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->isAdmin())->toBeTrue();

    $this->post('/login')->assertMethodNotAllowed();

    Livewire::test('pages::auth.login')
        ->set('email', 'admin@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin.layout'));

    $this->assertAuthenticatedAs($admin);
});

it('forbids non-admin users from accessing the hall layout designer', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.layout'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.reservations'))
        ->assertForbidden();
});

it('allows admin to switch preset templates and updates the active preset state', function () {
    $admin = User::factory()->admin()->create();
    $hall = Hall::factory()->create(['active_preset' => 'concert']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.hall-layout')
        ->assertSet('activePreset', 'concert')
        ->call('applyPreset', 'u_shape')
        ->assertSet('activePreset', 'u_shape')
        ->call('applyPreset', 'cinema')
        ->assertSet('activePreset', 'cinema');

    expect($hall->fresh()->active_preset)->toBe('cinema');
});

it('allows admin to customize seat colors, currency, and positions in front, back, and side sections', function () {
    $admin = User::factory()->admin()->create();
    $hall = Hall::factory()->create(['rows' => 6, 'columns' => 8, 'currency' => 'USD']);

    $this->actingAs($admin);

    // Update currency to EUR, change VIP section color from yellow (#eab308) to emerald (#10b981), and paint seats
    Livewire::test('pages::admin.hall-layout')
        ->call('setQuickCurrency', 'EUR')
        ->set('priceUpdateSection', SeatSection::Vip->value)
        ->call('selectSectionSwatch', '#10b981')
        ->set('toolMode', 'paint')
        ->set('brushSection', SeatSection::Vip->value)
        ->set('brushPrice', 180)
        ->set('brushPrefix', 'VIP')
        ->call('handleCellClick', 1, 4)
        ->set('brushSection', SeatSection::Left->value)
        ->set('brushPrice', 80)
        ->set('brushPrefix', 'L')
        ->call('handleCellClick', 3, 1)
        ->set('brushSection', SeatSection::Back->value)
        ->set('brushPrice', 55)
        ->set('brushPrefix', 'B')
        ->call('handleCellClick', 6, 5);

    expect($hall->fresh()->currency)->toBe('EUR')
        ->and($hall->fresh()->getSectionColor(SeatSection::Vip))->toBe('#10b981')
        ->and($hall->fresh()->formatPrice(180))->toBe('€180 EUR');

    $vipSeat = Seat::where('hall_id', $hall->id)->where('row', 1)->where('col', 4)->first();
    $leftSeat = Seat::where('hall_id', $hall->id)->where('row', 3)->where('col', 1)->first();
    $backSeat = Seat::where('hall_id', $hall->id)->where('row', 6)->where('col', 5)->first();

    expect($vipSeat)->not->toBeNull()
        ->and($vipSeat->section)->toBe(SeatSection::Vip)
        ->and($vipSeat->color)->toBe('#10b981')
        ->and($vipSeat->price)->toBe(180)
        ->and($vipSeat->seat_number)->toBe('VIP-1');

    expect($leftSeat)->not->toBeNull()
        ->and($leftSeat->section)->toBe(SeatSection::Left)
        ->and($leftSeat->price)->toBe(80)
        ->and($leftSeat->seat_number)->toBe('L-1');

    expect($backSeat)->not->toBeNull()
        ->and($backSeat->section)->toBe(SeatSection::Back)
        ->and($backSeat->price)->toBe(55)
        ->and($backSeat->seat_number)->toBe('B-1');

    // Edit individual seat number, price, and custom color
    Livewire::test('pages::admin.hall-layout')
        ->call('selectSeatForEdit', $vipSeat->id)
        ->set('editSeatNumber', 'VIP-101')
        ->set('editSeatColor', '#ef4444')
        ->set('editSeatPrice', 200)
        ->call('saveEditedSeat')
        ->assertHasNoErrors();

    $vipSeat->refresh();
    expect($vipSeat->seat_number)->toBe('VIP-101')
        ->and($vipSeat->color)->toBe('#ef4444')
        ->and($vipSeat->price)->toBe(200);

    // Erase the back seat to create an aisle
    Livewire::test('pages::admin.hall-layout')
        ->set('toolMode', 'erase')
        ->call('handleCellClick', 6, 5);

    $this->assertDatabaseMissing('seats', ['id' => $backSeat->id]);
});

it('allows users to select seats by clicking and reserve them with total price calculation', function () {
    $user = User::factory()->create();
    $hall = Hall::factory()->create(['rows' => 5, 'columns' => 5, 'currency' => 'USD']);

    $seat1 = Seat::factory()->for($hall)->create([
        'row' => 1,
        'col' => 2,
        'seat_number' => 'F-1',
        'section' => SeatSection::Front,
        'price' => 120,
    ]);

    $seat2 = Seat::factory()->for($hall)->create([
        'row' => 3,
        'col' => 1,
        'seat_number' => 'L-1',
        'section' => SeatSection::Left,
        'price' => 75,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::seat-reservation')
        ->call('toggleSeat', $seat1->id)
        ->call('toggleSeat', $seat2->id)
        ->assertSet('selectedSeatIds', [$seat1->id, $seat2->id])
        ->call('confirmReservation')
        ->assertSet('selectedSeatIds', []);

    $reservation = Reservation::where('user_id', $user->id)->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->total_price)->toBe(195)
        ->and($reservation->seats_count)->toBe(2)
        ->and($reservation->status)->toBe('confirmed');

    expect($seat1->fresh()->reserved_by_user_id)->toBe($user->id)
        ->and($seat2->fresh()->reserved_by_user_id)->toBe($user->id);
});

it('prevents a user from reserving a seat that is already reserved by another user', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $hall = Hall::factory()->create();

    $reservation = Reservation::factory()->for($firstUser)->for($hall)->create();

    $reservedSeat = Seat::factory()->for($hall)->create([
        'row' => 1,
        'col' => 1,
        'seat_number' => 'VIP-1',
        'section' => SeatSection::Vip,
        'price' => 150,
        'reserved_by_user_id' => $firstUser->id,
        'reservation_id' => $reservation->id,
    ]);

    $this->actingAs($secondUser);

    Livewire::test('pages::seat-reservation')
        ->call('toggleSeat', $reservedSeat->id)
        ->assertSet('selectedSeatIds', []);

    expect($reservedSeat->fresh()->reserved_by_user_id)->toBe($firstUser->id);
});

it('releases seats when a user cancels their reservation', function () {
    $user = User::factory()->create();
    $hall = Hall::factory()->create();

    $reservation = Reservation::factory()->for($user)->for($hall)->create([
        'status' => 'confirmed',
    ]);

    $seat = Seat::factory()->for($hall)->create([
        'row' => 2,
        'col' => 2,
        'seat_number' => 'C-1',
        'reserved_by_user_id' => $user->id,
        'reservation_id' => $reservation->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::my-reservations')
        ->call('cancelReservation', $reservation->id);

    expect($reservation->fresh()->status)->toBe('cancelled')
        ->and($seat->fresh()->isAvailable())->toBeTrue();
});
