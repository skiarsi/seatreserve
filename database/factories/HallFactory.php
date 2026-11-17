<?php

namespace Database\Factories;

use App\Enums\SeatSection;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hall>
 */
class HallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Grand Symphony & Conference Hall',
            'description' => 'Custom seating layout featuring VIP front rows, Front section, Left & Right side wings, Center block, and Back rows.',
            'stage_label' => 'STAGE (Front of Hall)',
            'currency' => 'USD',
            'active_preset' => 'concert',
            'section_colors' => SeatSection::defaultColorsMap(),
            'rows' => 9,
            'columns' => 14,
            'is_active' => true,
        ];
    }
}
