<?php

namespace App\Models;

use App\Enums\SeatSection;
use Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hall_id',
    'row',
    'col',
    'seat_number',
    'section',
    'color',
    'price',
    'is_active',
    'reserved_by_user_id',
    'reservation_id',
])]
class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row' => 'integer',
            'col' => 'integer',
            'section' => SeatSection::class,
            'price' => 'integer',
            'is_active' => 'boolean',
            'reserved_by_user_id' => 'integer',
            'reservation_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by_user_id');
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isReserved(): bool
    {
        return $this->reserved_by_user_id !== null || $this->reservation_id !== null;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->isReserved();
    }
}
