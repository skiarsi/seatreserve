<?php

use App\Enums\SeatSection;
use App\Models\Reservation;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Admin: Manage Reservations')] class extends Component
{
    use Toast;

    public string $search = '';

    public string $statusFilter = '';

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservations(): Collection
    {
        return Reservation::query()
            ->with(['user', 'hall', 'seats'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($sub) use ($term): void {
                    $sub->where('tracking_code', 'like', $term)
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('seats', fn ($sq) => $sq->where('seat_number', 'like', $term));
                });
            })
            ->latest('reserved_at')
            ->get();
    }

    public function cancelReservation(int $reservationId): void
    {
        $reservation = Reservation::findOrFail($reservationId);

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
        $this->info("Reservation {$reservation->tracking_code} has been cancelled and its seats released.");
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 border border-zinc-200">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider bg-zinc-900 text-white">
                    Admin Panel
                </span>
                <h1 class="text-xl font-bold tracking-tight text-zinc-900">Manage User Reservations</h1>
            </div>
            <p class="text-xs text-zinc-500">Search, inspect, and manage all seat reservations across the hall.</p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.layout') }}"
                wire:navigate
                class="h-9 px-3.5 inline-flex items-center text-xs font-medium bg-white hover:bg-zinc-100 text-zinc-900 border border-zinc-300 transition"
            >
                Layout & Pricing Designer
            </a>
            <a
                href="{{ route('home') }}"
                wire:navigate
                class="h-9 px-3.5 inline-flex items-center text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
            >
                View Hall Map
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white p-4 border border-zinc-200 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div class="sm:col-span-2 space-y-1">
            <label class="block text-xs font-medium text-zinc-700">Search by Tracking Code, User Name/Email, or Seat Number</label>
            <input
                type="text"
                wire:model.live.debounce.250ms="search"
                placeholder="e.g. SR-10024891, VIP-3, or user@example.com"
                class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
            />
        </div>

        <div class="space-y-1">
            <label class="block text-xs font-medium text-zinc-700">Reservation Status</label>
            <select
                wire:model.live="statusFilter"
                class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
            >
                <option value="">All Statuses</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    {{-- Reservations List --}}
    <div class="space-y-3">
        @forelse($this->reservations as $reservation)
            <div
                wire:key="admin-res-{{ $reservation->id }}"
                class="bg-white border border-zinc-200 p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4"
            >
                <div class="space-y-2">
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

                        <span class="font-semibold text-xs text-zinc-900">
                            {{ $reservation->user?->name ?? 'Deleted User' }}
                        </span>
                        <span class="text-xs text-zinc-500 font-mono">
                            ({{ $reservation->user?->email }})
                        </span>
                        <span class="text-xs text-zinc-400">
                            • {{ $reservation->reserved_at?->format('Y-m-d H:i') }}
                        </span>
                    </div>

                    @if($reservation->seats->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach($reservation->seats as $seat)
                                <span
                                    style="{{ $reservation->hall ? SeatSection::buildBadgeInlineStyle($reservation->hall->getSeatColor($seat)) : '' }}"
                                    class="px-2 py-0.5 border text-xs font-medium"
                                >
                                    <span class="font-mono font-bold">{{ $seat->seat_number }}</span>
                                    ({{ $seat->section->shortLabel() }} — {{ $reservation->hall ? $reservation->hall->formatPrice($seat->price) : '$'.$seat->price }})
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between lg:justify-end gap-4 border-t lg:border-t-0 pt-3 lg:pt-0 border-zinc-200">
                    <div class="text-right">
                        <div class="text-[11px] text-zinc-500">{{ $reservation->seats_count }} seats</div>
                        <div class="font-bold text-sm font-mono text-zinc-900">
                            {{ $reservation->hall ? $reservation->hall->formatPrice($reservation->total_price) : '$'.number_format($reservation->total_price) }}
                        </div>
                    </div>

                    @if($reservation->status === 'confirmed')
                        <button
                            type="button"
                            wire:click="cancelReservation({{ $reservation->id }})"
                            wire:confirm="Cancel this reservation and release its seats?"
                            class="h-8 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 text-xs font-medium transition cursor-pointer"
                        >
                            Cancel & Release Seats
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white border border-zinc-200 p-8 text-center text-xs text-zinc-500">
                No reservations found matching your filter criteria.
            </div>
        @endforelse
    </div>
</div>
