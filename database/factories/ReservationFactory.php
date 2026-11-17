<?php

namespace Database\Factories;

use App\Models\Hall;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hall_id' => Hall::factory(),
            'tracking_code' => 'SR-'.Str::upper(Str::random(8)),
            'total_price' => 150,
            'seats_count' => 1,
            'status' => 'confirmed',
            'reserved_at' => now(),
        ];
    }
}
