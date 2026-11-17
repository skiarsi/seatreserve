<?php

namespace Database\Factories;

use App\Enums\SeatSection;
use App\Models\Hall;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $row = fake()->numberBetween(1, 10);
        $col = fake()->numberBetween(1, 14);

        return [
            'hall_id' => Hall::factory(),
            'row' => $row,
            'col' => $col,
            'seat_number' => 'C-'.fake()->unique()->numberBetween(1, 9999),
            'section' => SeatSection::Center,
            'color' => SeatSection::Center->defaultColor(),
            'price' => SeatSection::Center->defaultPrice(),
            'is_active' => true,
            'reserved_by_user_id' => null,
            'reservation_id' => null,
        ];
    }

    public function section(SeatSection $section): static
    {
        return $this->state(fn (array $attributes): array => [
            'section' => $section,
            'color' => $section->defaultColor(),
            'price' => $section->defaultPrice(),
        ]);
    }
}
