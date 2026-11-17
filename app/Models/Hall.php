<?php

namespace App\Models;

use App\Enums\SeatSection;
use Database\Factories\HallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'stage_label', 'currency', 'active_preset', 'section_colors', 'rows', 'columns', 'is_active'])]
class Hall extends Model
{
    /** @use HasFactory<HallFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section_colors' => 'array',
            'rows' => 'integer',
            'columns' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Seat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Resolve the configured hex color for a given seat section.
     */
    public function getSectionColor(SeatSection|string $section): string
    {
        $enum = $section instanceof SeatSection ? $section : (SeatSection::tryFrom($section) ?? SeatSection::Center);
        $colors = is_array($this->section_colors) ? $this->section_colors : [];

        return $colors[$enum->value] ?? $enum->defaultColor();
    }

    /**
     * Resolve the hex color for a specific seat (uses seat override color if present, otherwise section color).
     */
    public function getSeatColor(Seat $seat): string
    {
        if (is_string($seat->color) && preg_match('/^#[0-9a-fA-F]{6}$/', $seat->color)) {
            return $seat->color;
        }

        return $this->getSectionColor($seat->section);
    }

    /**
     * Inline style for a seat on the grid.
     */
    public function getSeatStyle(Seat $seat): string
    {
        return SeatSection::buildSeatInlineStyle($this->getSeatColor($seat));
    }

    /**
     * Inline style for a section badge.
     */
    public function getSectionBadgeStyle(SeatSection|string $section): string
    {
        return SeatSection::buildBadgeInlineStyle($this->getSectionColor($section));
    }

    /**
     * Format a seat or reservation price with the admin-defined currency unit.
     */
    public function formatPrice(int $amount): string
    {
        $unit = trim($this->currency ?: 'USD');
        $formatted = number_format($amount);

        return match ($unit) {
            '$', 'USD' => '$'.$formatted.' USD',
            '€', 'EUR' => '€'.$formatted.' EUR',
            '£', 'GBP' => '£'.$formatted.' GBP',
            default => $formatted.' '.$unit,
        };
    }

    /**
     * Compact price format for seat cells on the grid.
     */
    public function formatCompactPrice(int $amount): string
    {
        $unit = trim($this->currency ?: 'USD');
        $formatted = number_format($amount);

        return match ($unit) {
            '$', 'USD' => '$'.$formatted,
            '€', 'EUR' => '€'.$formatted,
            '£', 'GBP' => '£'.$formatted,
            default => $formatted.' '.$unit,
        };
    }
}
