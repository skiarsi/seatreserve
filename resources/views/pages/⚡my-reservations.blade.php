<?php

use App\Enums\SeatSection;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('My Reservations')] class extends Component
{
    use Toast;

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        return Reservation::query()
            ->with(['hall', 'seats'])
            ->where('user_id', Auth::id())
            ->latest('reserved_at')
            ->get();
    }

    public function cancelReservation(int $reservationId): void
    {
        $reservation = Reservation::query()
            ->where('user_id', Auth::id())
            ->findOrFail($reservationId);

        if ($reservation->status === 'cancelled') {
            return;
        }

        DB::transaction(function () use ($reservation): void {
            Seat::query()
                ->where('reservation_id', $reservation->id)
                ->update([
                    'reserved_by_user_id' => null,
                    'reservation_id' => null,
                ]);

            $reservation->update([
                'status' => 'cancelled',
            ]);
        });

        unset($this->reservations);
        $this->info("Reservation {$reservation->tracking_code} has been cancelled and its seats are now available.");
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 border border-zinc-200">
        <div class="space-y-1">
            <h1 class="text-xl font-bold tracking-tight text-zinc-900">My Reservations</h1>
            <p class="text-xs text-zinc-500">View your booked seats, tracking codes, and reservation status.</p>
        </div>

        <a
            href="{{ route('home') }}"
            wire:navigate
            class="h-9 px-3.5 inline-flex items-center text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
        >
            Book More Seats
        </a>
    </div>

    @if($this->reservations->isEmpty())
        <div class="bg-white border border-zinc-200 p-10 text-center space-y-3">
            <h2 class="font-bold text-base text-zinc-900">You have no seat reservations yet</h2>
            <p class="text-xs text-zinc-500">Select your preferred seats from the hall map by seat number, section, and price.</p>
            <div class="pt-2">
                <a
                    href="{{ route('home') }}"
                    wire:navigate
                    class="h-9 px-4 inline-flex items-center text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
                >
                    Open Hall Map
                </a>
            </div>
        </div>
    @else
        <div class="space-y-3">
            @foreach($this->reservations as $reservation)
                <div
                    wire:key="my-res-{{ $reservation->id }}"
                    class="bg-white border border-zinc-200 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                    <div class="space-y-2.5">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="font-mono font-bold text-xs px-2.5 py-1 bg-zinc-900 text-white">
                                {{ $reservation->tracking_code }}
                            </span>

                            @if($reservation->status === 'confirmed')
                                <span class="px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider bg-zinc-100 text-zinc-900 border border-zinc-300">
                                    Confirmed
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider bg-zinc-100 text-zinc-400 border border-zinc-200 line-through">
                                    Cancelled
                                </span>
                            @endif

                            <span class="text-xs font-semibold text-zinc-800">
                                {{ $reservation->hall?->name ?? 'Main Hall' }}
                            </span>

                            <span class="text-xs text-zinc-400">
                                • {{ $reservation->reserved_at?->format('Y-m-d H:i') }}
                            </span>
                        </div>

                        @if($reservation->seats->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-xs font-medium text-zinc-500 mr-1">Seats:</span>
                                @foreach($reservation->seats as $seat)
                                    <span
                                        style="{{ $reservation->hall ? SeatSection::buildBadgeInlineStyle($reservation->hall->getSeatColor($seat)) : '' }}"
                                        class="px-2 py-0.5 border text-xs font-medium"
                                    >
                                        <span class="font-mono font-bold">{{ $seat->seat_number }}</span>
                                        <span>({{ $seat->section->shortLabel() }} — {{ $reservation->hall ? $reservation->hall->formatPrice($seat->price) : '$'.$seat->price }})</span>
                                    </span>
                                @endforeach
                            </div>
                        @elseif($reservation->status === 'cancelled')
                            <p class="text-xs text-zinc-400">Seats from this reservation have been released.</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between md:justify-end gap-4 border-t md:border-t-0 pt-3 md:pt-0 border-zinc-200">
                        <div class="text-right">
                            <div class="text-[11px] text-zinc-500">Total ({{ $reservation->seats_count }} seats)</div>
                            <div class="font-bold text-sm font-mono text-zinc-900">
                                {{ $reservation->hall ? $reservation->hall->formatPrice($reservation->total_price) : '$'.number_format($reservation->total_price) }}
                            </div>
                        </div>

                        @if($reservation->status === 'confirmed')
                            <button
                                type="button"
                                wire:click="cancelReservation({{ $reservation->id }})"
                                wire:confirm="Are you sure you want to cancel this reservation and release the seats?"
                                class="h-8 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 text-xs font-medium transition cursor-pointer"
                            >
                                Cancel Booking
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
