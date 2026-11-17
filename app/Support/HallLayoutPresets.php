<?php

namespace App\Support;

use App\Enums\SeatSection;
use App\Models\Hall;
use App\Models\Seat;
use Illuminate\Support\Facades\DB;

class HallLayoutPresets
{
    /**
     * Apply a named preset layout to the given hall.
     */
    public static function apply(Hall $hall, string $preset): void
    {
        DB::transaction(function () use ($hall, $preset): void {
            $hall->seats()->delete();

            if (! is_array($hall->section_colors) || $hall->section_colors === []) {
                $hall->section_colors = SeatSection::defaultColorsMap();
            }

            match ($preset) {
                'u_shape' => self::buildUShapeLayout($hall),
                'cinema' => self::buildCinemaLayout($hall),
                default => self::buildConcertLayout($hall),
            };
        });
    }

    /**
     * Concert / Theater layout with VIP Front, Front rows, Left & Right wings, Center block, and Back rows.
     */
    private static function buildConcertLayout(Hall $hall): void
    {
        $hall->update([
            'active_preset' => 'concert',
            'section_colors' => $hall->section_colors ?: SeatSection::defaultColorsMap(),
            'rows' => 9,
            'columns' => 14,
        ]);

        $counters = [
            SeatSection::Vip->value => 1,
            SeatSection::Front->value => 1,
            SeatSection::Left->value => 1,
            SeatSection::Right->value => 1,
            SeatSection::Center->value => 1,
            SeatSection::Back->value => 1,
        ];

        $now = now();
        $records = [];

        for ($row = 1; $row <= 9; $row++) {
            for ($col = 1; $col <= 14; $col++) {
                $section = null;

                if ($row === 1 && $col >= 5 && $col <= 10) {
                    $section = SeatSection::Vip;
                } elseif ($row === 2 && $col >= 5 && $col <= 10) {
                    $section = SeatSection::Front;
                } elseif ($row >= 2 && $row <= 7 && $col >= 1 && $col <= 3) {
                    $section = SeatSection::Left;
                } elseif ($row >= 2 && $row <= 7 && $col >= 12 && $col <= 14) {
                    $section = SeatSection::Right;
                } elseif ($row >= 4 && $row <= 6 && $col >= 5 && $col <= 10) {
                    $section = SeatSection::Center;
                } elseif ($row >= 8 && $row <= 9 && $col >= 2 && $col <= 13 && $col !== 4 && $col !== 11) {
                    $section = SeatSection::Back;
                }

                if ($section === null) {
                    continue;
                }

                $number = $section->prefix().'-'.$counters[$section->value]++;

                $records[] = [
                    'hall_id' => $hall->id,
                    'row' => $row,
                    'col' => $col,
                    'seat_number' => $number,
                    'section' => $section->value,
                    'color' => $hall->getSectionColor($section),
                    'price' => $section->defaultPrice(),
                    'is_active' => true,
                    'reserved_by_user_id' => null,
                    'reservation_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Seat::insert($records);
    }

    /**
     * U-Shape / Conference layout with Front VIP/Front seats, Left & Right side blocks, and Back row.
     */
    private static function buildUShapeLayout(Hall $hall): void
    {
        $hall->update([
            'active_preset' => 'u_shape',
            'section_colors' => $hall->section_colors ?: SeatSection::defaultColorsMap(),
            'rows' => 8,
            'columns' => 12,
        ]);

        $counters = [
            SeatSection::Vip->value => 1,
            SeatSection::Front->value => 1,
            SeatSection::Left->value => 1,
            SeatSection::Right->value => 1,
            SeatSection::Back->value => 1,
        ];

        $now = now();
        $records = [];

        for ($row = 1; $row <= 8; $row++) {
            for ($col = 1; $col <= 12; $col++) {
                $section = null;

                if ($row === 1 && $col >= 4 && $col <= 9) {
                    $section = SeatSection::Vip;
                } elseif ($row === 2 && $col >= 4 && $col <= 9) {
                    $section = SeatSection::Front;
                } elseif ($row >= 2 && $row <= 6 && $col >= 1 && $col <= 2) {
                    $section = SeatSection::Left;
                } elseif ($row >= 2 && $row <= 6 && $col >= 11 && $col <= 12) {
                    $section = SeatSection::Right;
                } elseif ($row >= 7 && $row <= 8 && $col >= 3 && $col <= 10) {
                    $section = SeatSection::Back;
                }

                if ($section === null) {
                    continue;
                }

                $number = $section->prefix().'-'.$counters[$section->value]++;

                $records[] = [
                    'hall_id' => $hall->id,
                    'row' => $row,
                    'col' => $col,
                    'seat_number' => $number,
                    'section' => $section->value,
                    'color' => $hall->getSectionColor($section),
                    'price' => $section->defaultPrice(),
                    'is_active' => true,
                    'reserved_by_user_id' => null,
                    'reservation_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Seat::insert($records);
    }

    /**
     * Classic Cinema layout with Front, Center, and Back rows and central aisle.
     */
    private static function buildCinemaLayout(Hall $hall): void
    {
        $hall->update([
            'active_preset' => 'cinema',
            'section_colors' => $hall->section_colors ?: SeatSection::defaultColorsMap(),
            'rows' => 8,
            'columns' => 13,
        ]);

        $counters = [
            SeatSection::Front->value => 1,
            SeatSection::Center->value => 1,
            SeatSection::Back->value => 1,
        ];

        $now = now();
        $records = [];

        for ($row = 1; $row <= 8; $row++) {
            for ($col = 1; $col <= 13; $col++) {
                if ($col === 7) {
                    continue;
                }

                $section = match (true) {
                    $row <= 2 => SeatSection::Front,
                    $row <= 6 => SeatSection::Center,
                    default => SeatSection::Back,
                };

                $number = $section->prefix().'-'.$counters[$section->value]++;

                $records[] = [
                    'hall_id' => $hall->id,
                    'row' => $row,
                    'col' => $col,
                    'seat_number' => $number,
                    'section' => $section->value,
                    'color' => $hall->getSectionColor($section),
                    'price' => $section->defaultPrice(),
                    'is_active' => true,
                    'reserved_by_user_id' => null,
                    'reservation_id' => $hall->id ? null : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Seat::insert($records);
    }
}
