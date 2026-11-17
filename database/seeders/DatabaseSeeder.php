<?php

namespace Database\Seeders;

use App\Models\Hall;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\User;
use App\Support\HallLayoutPresets;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Hall Administrator',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $regularUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Alex Morgan',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Sarah Jenkins',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        $hall = Hall::firstOrCreate(
            ['name' => 'Grand Symphony & Conference Hall'],
            [
                'description' => 'Multi-zone venue featuring VIP front rows, Front section, Left & Right side wings, Center seating, and Back rows.',
                'stage_label' => 'STAGE (Front of Hall)',
                'currency' => 'USD',
                'rows' => 9,
                'columns' => 14,
                'is_active' => true,
            ]
        );

        if ($hall->seats()->count() === 0) {
            HallLayoutPresets::apply($hall, 'concert');

            $sampleSeats = Seat::query()
                ->where('hall_id', $hall->id)
                ->whereIn('seat_number', ['VIP-3', 'VIP-4', 'C-8'])
                ->get();

            if ($sampleSeats->isNotEmpty()) {
                $reservation = Reservation::create([
                    'user_id' => $regularUser->id,
                    'hall_id' => $hall->id,
                    'tracking_code' => 'SR-10024891',
                    'total_price' => (int) $sampleSeats->sum('price'),
                    'seats_count' => $sampleSeats->count(),
                    'status' => 'confirmed',
                    'reserved_at' => now()->subMinutes(45),
                ]);

                Seat::whereIn('id', $sampleSeats->pluck('id'))->update([
                    'reserved_by_user_id' => $regularUser->id,
                    'reservation_id' => $reservation->id,
                ]);
            }
        }
    }
}
