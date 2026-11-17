<?php

namespace App\Enums;

enum SeatSection: string
{
    case Vip = 'vip';
    case Front = 'front';
    case Center = 'center';
    case Left = 'left';
    case Right = 'right';
    case Back = 'back';

    public function label(): string
    {
        return match ($this) {
            self::Vip => 'VIP (Front Row)',
            self::Front => 'Front Section',
            self::Center => 'Center Section',
            self::Left => 'Left Wing',
            self::Right => 'Right Wing',
            self::Back => 'Back Section',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Vip => 'VIP',
            self::Front => 'Front',
            self::Center => 'Center',
            self::Left => 'Left',
            self::Right => 'Right',
            self::Back => 'Back',
        };
    }

    public function defaultPrice(): int
    {
        return match ($this) {
            self::Vip => 150,
            self::Front => 120,
            self::Center => 90,
            self::Left => 75,
            self::Right => 75,
            self::Back => 50,
        };
    }

    public function defaultColor(): string
    {
        return match ($this) {
            self::Vip => '#eab308',
            self::Front => '#8b5cf6',
            self::Center => '#0ea5e9',
            self::Left => '#14b8a6',
            self::Right => '#6366f1',
            self::Back => '#f43f5e',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function defaultColorsMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->defaultColor();
        }

        return $map;
    }

    /**
     * Curated color palette swatches for quick admin selection.
     *
     * @return array<string, string>
     */
    public static function colorPalette(): array
    {
        return [
            'Yellow' => '#eab308',
            'Orange' => '#f97316',
            'Red' => '#ef4444',
            'Rose' => '#f43f5e',
            'Pink' => '#ec4899',
            'Violet' => '#8b5cf6',
            'Indigo' => '#6366f1',
            'Blue' => '#2563eb',
            'Sky' => '#0ea5e9',
            'Teal' => '#14b8a6',
            'Emerald' => '#10b981',
            'Lime' => '#84cc16',
        ];
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Vip => 'VIP',
            self::Front => 'F',
            self::Center => 'C',
            self::Left => 'L',
            self::Right => 'R',
            self::Back => 'B',
        };
    }

    /**
     * Build inline CSS style for a seat based on its resolved hex color.
     */
    public static function buildSeatInlineStyle(string $hexColor): string
    {
        $safeColor = preg_match('/^#[0-9a-fA-F]{6}$/', trim($hexColor)) ? trim($hexColor) : '#0ea5e9';

        return "background-color: color-mix(in srgb, {$safeColor} 20%, white); border-color: {$safeColor}; color: color-mix(in srgb, {$safeColor} 55%, black);";
    }

    /**
     * Build inline CSS style for a section badge based on its resolved hex color.
     */
    public static function buildBadgeInlineStyle(string $hexColor): string
    {
        $safeColor = preg_match('/^#[0-9a-fA-F]{6}$/', trim($hexColor)) ? trim($hexColor) : '#0ea5e9';

        return "background-color: color-mix(in srgb, {$safeColor} 18%, white); border-color: color-mix(in srgb, {$safeColor} 65%, white); color: color-mix(in srgb, {$safeColor} 50%, black);";
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section): array => [
                'id' => $section->value,
                'name' => $section->label(),
            ],
            self::cases()
        );
    }
}
