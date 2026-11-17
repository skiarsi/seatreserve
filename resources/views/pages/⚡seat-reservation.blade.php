<?php

use App\Enums\SeatSection;
use App\Models\Hall;
use App\Models\Reservation;
use App\Models\Seat;
use App\Support\HallLayoutPresets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Hall Map & Seat Reservation')] class extends Component
{
    use Toast;

    public int $hallId = 0;

    /**
     * @var array<int, int>
     */
    public array $selectedSeatIds = [];

    public string $searchNumber = '';

    public string $filterSection = '';

    public ?int $maxPrice = null;

    public ?string $lastTrackingCode = null;

    public function mount(): void
    {
        $hall = Hall::query()->where('is_active', true)->first();

        if (! $hall) {
            $hall = Hall::create([
                'name' => 'Grand Symphony & Conference Hall',
                'description' => 'Multi-zone venue featuring VIP front rows, Front section, Left & Right side wings, Center seating, and Back rows.',
                'stage_label' => 'STAGE (Front of Hall)',
                'currency' => 'USD',
                'active_preset' => 'concert',
                'section_colors' => SeatSection::defaultColorsMap(),
                'rows' => 9,
                'columns' => 14,
                'is_active' => true,
            ]);
            HallLayoutPresets::apply($hall, 'concert');
        }

        $this->hallId = $hall->id;
    }

    #[Computed]
    public function hall(): Hall
    {
        return Hall::findOrFail($this->hallId);
    }

    /**
     * @return Collection<int, Seat>
     */
    #[Computed]
    public function seats(): Collection
    {
        return Seat::query()
            ->where('hall_id', $this->hallId)
            ->orderBy('row')
            ->orderBy('col')
            ->get();
    }

    /**
     * Map seats by "row-col" key for O(1) lookup in the 2D grid.
     *
     * @return array<string, Seat>
     */
    #[Computed]
    public function seatGrid(): array
    {
        $grid = [];

        foreach ($this->seats as $seat) {
            $grid[$seat->row.'-'.$seat->col] = $seat;
        }

        return $grid;
    }

    /**
     * @return Collection<int, Seat>
     */
    #[Computed]
    public function selectedSeats(): Collection
    {
        if ($this->selectedSeatIds === []) {
            return new Collection;
        }

        return $this->seats
            ->whereIn('id', $this->selectedSeatIds)
            ->values();
    }

    #[Computed]
    public function totalSelectedPrice(): int
    {
        return (int) $this->selectedSeats->sum('price');
    }

    public function matchesFilter(Seat $seat): bool
    {
        if ($this->searchNumber !== '' && ! str_contains(Str::lower($seat->seat_number), Str::lower(trim($this->searchNumber)))) {
            return false;
        }

        if ($this->filterSection !== '' && $seat->section->value !== $this->filterSection) {
            return false;
        }

        if ($this->maxPrice !== null && $this->maxPrice > 0 && $seat->price > $this->maxPrice) {
            return false;
        }

        return true;
    }

    public function resetFilters(): void
    {
        $this->searchNumber = '';
        $this->filterSection = '';
        $this->maxPrice = null;
    }

    public function toggleSeat(int $seatId): void
    {
        $seat = Seat::query()
            ->where('hall_id', $this->hallId)
            ->find($seatId);

        if (! $seat || ! $seat->isAvailable()) {
            $this->error('This seat is already reserved or unavailable.');
            $this->selectedSeatIds = array_values(array_diff($this->selectedSeatIds, [$seatId]));

            return;
        }

        if (in_array($seatId, $this->selectedSeatIds, true)) {
            $this->selectedSeatIds = array_values(array_diff($this->selectedSeatIds, [$seatId]));
        } else {
            $this->selectedSeatIds[] = $seatId;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedSeatIds = [];
    }

    public function confirmReservation(): void
    {
        if (! Auth::check()) {
            $this->warning('Please sign in to complete your seat reservation.');
            $this->redirectRoute('login', navigate: true);

            return;
        }

        if ($this->selectedSeatIds === []) {
            $this->error('Please select at least one seat from the hall map.');

            return;
        }

        $user = Auth::user();

        try {
            $reservation = DB::transaction(function () use ($user): Reservation {
                $seats = Seat::query()
                    ->where('hall_id', $this->hallId)
                    ->whereIn('id', $this->selectedSeatIds)
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($this->selectedSeatIds)) {
                    throw new RuntimeException('Some selected seats no longer exist in the hall.');
                }

                foreach ($seats as $seat) {
                    if (! $seat->isAvailable()) {
                        throw new RuntimeException("Seat {$seat->seat_number} has already been reserved.");
                    }
                }

                $trackingCode = 'SR-'.Str::upper(Str::random(8));

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'hall_id' => $this->hallId,
                    'tracking_code' => $trackingCode,
                    'total_price' => (int) $seats->sum('price'),
                    'seats_count' => $seats->count(),
                    'status' => 'confirmed',
                    'reserved_at' => now(),
                ]);

                Seat::query()
                    ->whereIn('id', $seats->pluck('id'))
                    ->update([
                        'reserved_by_user_id' => $user->id,
                        'reservation_id' => $reservation->id,
                    ]);

                return $reservation;
            });

            $this->selectedSeatIds = [];
            $this->lastTrackingCode = $reservation->tracking_code;
            unset($this->seats, $this->seatGrid, $this->selectedSeats, $this->totalSelectedPrice);

            $this->success("Reservation confirmed! Tracking code: {$reservation->tracking_code}");
        } catch (RuntimeException $e) {
            unset($this->seats, $this->seatGrid, $this->selectedSeats, $this->totalSelectedPrice);
            $this->error($e->getMessage());
        }
    }
};
?>

<div class="space-y-5">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 border border-zinc-200">
        <div class="space-y-0.5">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider bg-zinc-900 text-white">
                    Live Booking
                </span>
                <h1 class="text-lg font-bold tracking-tight text-zinc-900">{{ $this->hall->name }}</h1>
                <span class="px-2 py-0.5 text-xs font-mono bg-zinc-100 border border-zinc-300 text-zinc-700">
                    Currency: {{ $this->hall->currency }}
                </span>
            </div>
            @if($this->hall->description)
                <p class="text-xs text-zinc-500">{{ $this->hall->description }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @auth
                <a
                    href="{{ route('reservations.mine') }}"
                    wire:navigate
                    class="h-8 px-3 inline-flex items-center gap-1.5 text-xs font-medium bg-white hover:bg-zinc-100 text-zinc-900 border border-zinc-300 transition"
                >
                    My Reservations
                </a>
                @if(auth()->user()->isAdmin())
                    <a
                        href="{{ route('admin.layout') }}"
                        wire:navigate
                        class="h-8 px-3 inline-flex items-center gap-1.5 text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
                    >
                        Configure Layout & Pricing
                    </a>
                @endif
            @else
                <a
                    href="{{ route('login') }}"
                    wire:navigate
                    class="h-8 px-3 inline-flex items-center gap-1.5 text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
                >
                    Sign In to Reserve
                </a>
            @endauth
        </div>
    </div>

    {{-- Confirmation Banner --}}
    @if($lastTrackingCode)
        <div class="bg-zinc-900 text-white p-4 border border-zinc-900 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="space-y-0.5">
                <div class="font-bold text-sm">Your seat reservation is confirmed!</div>
                <div class="text-xs text-zinc-300">
                    Tracking Code:
                    <span class="font-mono font-bold bg-zinc-800 px-2 py-0.5 text-white border border-zinc-700">{{ $lastTrackingCode }}</span>
                </div>
            </div>
            <a
                href="{{ route('reservations.mine') }}"
                wire:navigate
                class="h-8 px-3 inline-flex items-center text-xs font-medium bg-white text-zinc-900 hover:bg-zinc-100 transition"
            >
                View My Tickets
            </a>
        </div>
    @endif

    {{-- Top Bar: Seat Types, Colors & Prices Legend + Search/Filters --}}
    <div class="bg-white p-4 border border-zinc-200 space-y-3">
        {{-- Prominent Seat Types & Prices Header Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-zinc-200 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-zinc-800 uppercase tracking-wider text-[11px] mr-1">Seat Types & Prices (Click to filter):</span>
                @foreach(SeatSection::cases() as $section)
                    @php
                        $sectionSeats = $this->seats->where('section', $section)->where('is_active', true);
                        $minSectionPrice = $sectionSeats->min('price');
                        $secColor = $this->hall->getSectionColor($section);
                    @endphp
                    @if($sectionSeats->isNotEmpty())
                        <button
                            type="button"
                            wire:click="$set('filterSection', '{{ $filterSection === $section->value ? '' : $section->value }}')"
                            style="{{ $this->hall->getSectionBadgeStyle($section) }}"
                            class="px-2.5 py-1 border text-xs font-medium transition cursor-pointer flex items-center gap-1.5 {{ $filterSection === $section->value ? 'ring-2 ring-zinc-900' : '' }}"
                        >
                            <span class="w-3 h-3 rounded-full inline-block border border-black/20" style="background-color: {{ $secColor }};"></span>
                            <span class="font-bold">{{ $section->label() }}:</span>
                            <span class="font-mono font-bold">{{ $this->hall->formatPrice((int) $minSectionPrice) }}</span>
                        </button>
                    @endif
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3 text-zinc-600">
                <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded-full bg-emerald-600 inline-block"></span>
                    <span>Selected</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded-full bg-teal-700 inline-block"></span>
                    <span>Your Booking</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded-full bg-zinc-200 border border-zinc-400 inline-block"></span>
                    <span>Reserved</span>
                </div>
            </div>
        </div>

        {{-- Search & Filter Inputs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div class="space-y-1">
                <label class="block text-xs font-medium text-zinc-700">Search by Seat Number</label>
                <input
                    type="text"
                    wire:model.live.debounce.200ms="searchNumber"
                    placeholder="e.g. VIP-1, L-3, C-5"
                    class="w-full h-8 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-medium text-zinc-700">Filter by Hall Section</label>
                <select
                    wire:model.live="filterSection"
                    class="w-full h-8 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                >
                    <option value="">All Hall Sections</option>
                    @foreach(SeatSection::cases() as $section)
                        <option value="{{ $section->value }}">{{ $section->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-medium text-zinc-700">Max Price ({{ $this->hall->currency }})</label>
                <input
                    type="number"
                    wire:model.live.debounce.300ms="maxPrice"
                    step="5"
                    placeholder="e.g. 120"
                    class="w-full h-8 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
            </div>

            <div>
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="w-full h-8 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 border border-zinc-300 text-xs font-medium transition cursor-pointer"
                >
                    Reset Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Main Layout: Compact Circular Hall Map + Selection Summary Menu --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

        {{-- Visual Hall Map (8 cols on XL) --}}
        <div class="xl:col-span-8 bg-white border border-zinc-200 p-4 sm:p-5 overflow-hidden">
            {{-- Compact Stage Indicator (Front of Hall) --}}
            <div class="max-w-md mx-auto mb-5">
                <div class="w-full py-1.5 px-4 bg-zinc-900 text-white border-b-2 border-zinc-500 text-center">
                    <div class="text-xs font-bold uppercase tracking-widest">
                        {{ $this->hall->stage_label }}
                    </div>
                </div>
            </div>

            {{-- Compact 2D Circular Seat Grid --}}
            <div class="overflow-x-auto py-1">
                <div class="min-w-fit mx-auto flex flex-col gap-1.5 items-center">
                    @for($row = 1; $row <= $this->hall->rows; $row++)
                        <div class="flex items-center gap-1.5" wire:key="hall-row-{{ $row }}">
                            {{-- Left Row Label --}}
                            <div class="w-10 text-right pr-1.5 text-[10px] font-mono font-semibold text-zinc-400 shrink-0">
                                R{{ $row }}
                            </div>

                            @for($col = 1; $col <= $this->hall->columns; $col++)
                                @php
                                    $seat = $this->seatGrid[$row.'-'.$col] ?? null;
                                @endphp

                                @if($seat && $seat->is_active)
                                    @php
                                        $isSelected = in_array($seat->id, $this->selectedSeatIds, true);
                                        $isReservedByMe = auth()->check() && $seat->reserved_by_user_id === auth()->id();
                                        $isReserved = $seat->isReserved();
                                        $matches = $this->matchesFilter($seat);
                                    @endphp

                                    <button
                                        type="button"
                                        wire:key="seat-cell-{{ $seat->id }}"
                                        @if(! $isReserved)
                                            wire:click="toggleSeat({{ $seat->id }})"
                                        @else
                                            disabled
                                        @endif
                                        @if(! $isSelected && ! $isReserved)
                                            style="{{ $this->hall->getSeatStyle($seat) }}"
                                        @endif
                                        title="Seat {{ $seat->seat_number }} | {{ $seat->section->label() }} | Price: {{ $this->hall->formatPrice($seat->price) }}"
                                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-full border-2 flex items-center justify-center p-0.5 transition-all duration-150 select-none relative
                                            {{ ! $matches ? 'opacity-20 scale-90' : '' }}
                                            @if($isSelected)
                                                bg-emerald-600 border-emerald-800 text-white shadow-xs scale-110 ring-2 ring-emerald-400 cursor-pointer
                                            @elseif($isReservedByMe)
                                                bg-teal-700 border-teal-900 text-white cursor-not-allowed
                                            @elseif($isReserved)
                                                bg-zinc-200 border-zinc-300 text-zinc-400 cursor-not-allowed
                                            @else
                                                hover:brightness-95 cursor-pointer
                                            @endif
                                        "
                                    >
                                        <span class="text-[9px] font-bold leading-none truncate max-w-full font-mono">
                                            {{ $seat->seat_number }}
                                        </span>
                                    </button>
                                @else
                                    {{-- Empty Aisle / Walkway space --}}
                                    <div
                                        wire:key="empty-cell-{{ $row }}-{{ $col }}"
                                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-[10px] text-zinc-200 select-none"
                                    >
                                        ·
                                    </div>
                                @endif
                            @endfor

                            {{-- Right Row Label --}}
                            <div class="w-10 text-left pl-1.5 text-[10px] font-mono font-semibold text-zinc-400 shrink-0">
                                R{{ $row }}
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Back of Hall Indicator --}}
            <div class="mt-4 text-center">
                <span class="inline-block px-3 py-0.5 bg-zinc-100 border border-zinc-200 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">
                    Rear of Hall (Back Rows)
                </span>
            </div>
        </div>

        {{-- Selection & Price Menu Sidebar (4 cols on XL) --}}
        <div class="xl:col-span-4 space-y-4">
            <div class="bg-white border border-zinc-200 p-4 space-y-4 sticky top-20">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2.5">
                    <h2 class="font-bold text-sm text-zinc-900">Selected Seats & Price Details</h2>
                    <span class="px-2 py-0.5 text-xs font-mono font-bold bg-zinc-900 text-white">
                        {{ $this->selectedSeats->count() }} selected
                    </span>
                </div>

                @if($this->selectedSeats->isEmpty())
                    <div class="py-6 text-center space-y-1.5 text-zinc-500">
                        <p class="text-xs font-medium text-zinc-700">Click any circular seat on the map</p>
                        <p class="text-[11px]">When you click a seat, its exact seat number, section type, and price ({{ $this->hall->currency }}) will appear here.</p>
                    </div>
                @else
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        @foreach($this->selectedSeats as $selectedSeat)
                            <div
                                wire:key="selected-seat-{{ $selectedSeat->id }}"
                                class="flex items-center justify-between p-2.5 bg-zinc-50 border border-zinc-200"
                            >
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-3 h-3 rounded-full inline-block border border-black/20"
                                            style="background-color: {{ $this->hall->getSeatColor($selectedSeat) }};"
                                        ></span>
                                        <span class="font-bold text-xs font-mono text-zinc-900">{{ $selectedSeat->seat_number }}</span>
                                        <span
                                            style="{{ $this->hall->getSectionBadgeStyle($selectedSeat->section) }}"
                                            class="text-[10px] px-1.5 py-0.5 border font-medium"
                                        >
                                            {{ $selectedSeat->section->label() }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-zinc-500">
                                        Row {{ $selectedSeat->row }}, Column {{ $selectedSeat->col }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <div class="text-right font-mono font-bold text-xs text-zinc-900">
                                        {{ $this->hall->formatPrice($selectedSeat->price) }}
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="toggleSeat({{ $selectedSeat->id }})"
                                        class="w-6 h-6 bg-white hover:bg-zinc-200 border border-zinc-300 text-zinc-700 flex items-center justify-center text-xs cursor-pointer"
                                        title="Remove seat"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-zinc-200 pt-3 space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-zinc-500">Selected Seats:</span>
                            <span class="font-semibold text-zinc-900">{{ $this->selectedSeats->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold text-zinc-900">Total Price:</span>
                            <span class="font-bold text-base font-mono text-zinc-900">
                                {{ $this->hall->formatPrice($this->totalSelectedPrice) }}
                            </span>
                        </div>

                        <div class="pt-1 space-y-2">
                            <button
                                type="button"
                                wire:click="confirmReservation"
                                class="w-full h-9 px-4 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium transition cursor-pointer flex items-center justify-center"
                            >
                                <span wire:loading.remove wire:target="confirmReservation">Confirm & Book Seats</span>
                                <span wire:loading wire:target="confirmReservation">Processing booking...</span>
                            </button>

                            <button
                                type="button"
                                wire:click="clearSelection"
                                class="w-full h-8 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 border border-zinc-300 text-xs font-medium transition cursor-pointer"
                            >
                                Clear Selection
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Hall Stats Summary --}}
                <div class="border-t border-zinc-200 pt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="bg-zinc-50 border border-zinc-200 p-2">
                        <div class="text-zinc-500 text-[11px]">Total Seats</div>
                        <div class="font-bold text-sm mt-0.5 text-zinc-900">{{ $this->seats->where('is_active', true)->count() }}</div>
                    </div>
                    <div class="bg-zinc-50 border border-zinc-200 p-2">
                        <div class="text-zinc-500 text-[11px]">Available</div>
                        <div class="font-bold text-sm mt-0.5 text-zinc-900">
                            {{ $this->seats->filter(fn (\App\Models\Seat $s) => $s->isAvailable())->count() }}
                        </div>
                    </div>
                    <div class="bg-zinc-50 border border-zinc-200 p-2">
                        <div class="text-zinc-500 text-[11px]">Reserved</div>
                        <div class="font-bold text-sm mt-0.5 text-zinc-900">
                            {{ $this->seats->filter(fn (\App\Models\Seat $s) => $s->isReserved())->count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
