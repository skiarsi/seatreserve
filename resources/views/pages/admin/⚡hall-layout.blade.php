<?php

use App\Enums\SeatSection;
use App\Models\Hall;
use App\Models\Seat;
use App\Support\HallLayoutPresets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Admin: Hall Layout & Pricing Designer')] class extends Component
{
    use Toast;

    public int $hallId = 0;

    // Hall settings
    public string $name = '';

    public string $stageLabel = '';

    public string $description = '';

    public string $currency = 'USD';

    public string $activePreset = 'concert';

    public int $rows = 9;

    public int $columns = 14;

    // Interactive Grid Tool Mode: 'paint' | 'edit' | 'erase'
    public string $toolMode = 'paint';

    // Paint Brush Settings
    public string $brushSection = 'front';

    public int $brushPrice = 120;

    public string $brushPrefix = 'F';

    public string $brushColor = '#8b5cf6';

    // Single Seat Editor State
    public ?int $editingSeatId = null;

    public string $editSeatNumber = '';

    public string $editSeatSection = 'center';

    public string $editSeatColor = '#0ea5e9';

    public int $editSeatPrice = 90;

    public bool $editSeatActive = true;

    // Bulk Row / Column Generator
    public int $bulkTargetRow = 1;

    public int $bulkTargetCol = 1;

    // Bulk Section Pricing & Color
    public string $priceUpdateSection = 'vip';

    public int $priceUpdateAmount = 150;

    public string $sectionColorUpdate = '#eab308';

    public function mount(): void
    {
        $hall = Hall::query()->first();

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
        $this->syncHallFields($hall);
    }

    private function syncHallFields(Hall $hall): void
    {
        $this->name = $hall->name;
        $this->stageLabel = $hall->stage_label;
        $this->description = (string) $hall->description;
        $this->currency = $hall->currency ?: 'USD';
        $this->activePreset = $hall->active_preset ?: 'concert';
        $this->rows = $hall->rows;
        $this->columns = $hall->columns;
        $this->brushColor = $hall->getSectionColor($this->brushSection);
        $this->sectionColorUpdate = $hall->getSectionColor($this->priceUpdateSection);
    }

    public function updatedBrushSection(string $value): void
    {
        $section = SeatSection::tryFrom($value);

        if ($section) {
            $sectionSeatPrice = Seat::query()
                ->where('hall_id', $this->hallId)
                ->where('section', $section->value)
                ->value('price');

            $this->brushPrice = $sectionSeatPrice !== null ? (int) $sectionSeatPrice : $section->defaultPrice();
            $this->brushPrefix = $section->prefix();
            $this->brushColor = $this->hall->getSectionColor($section);
        }
    }

    public function updatedPriceUpdateSection(string $value): void
    {
        $section = SeatSection::tryFrom($value);

        if ($section) {
            $sectionSeatPrice = Seat::query()
                ->where('hall_id', $this->hallId)
                ->where('section', $section->value)
                ->value('price');

            $this->priceUpdateAmount = $sectionSeatPrice !== null ? (int) $sectionSeatPrice : $section->defaultPrice();
            $this->sectionColorUpdate = $this->hall->getSectionColor($section);
        }
    }

    public function updatedEditSeatSection(string $value): void
    {
        $section = SeatSection::tryFrom($value);

        if ($section) {
            $this->editSeatColor = $this->hall->getSectionColor($section);
        }
    }

    public function setQuickCurrency(string $unit): void
    {
        $this->currency = $unit;
        $this->hall->update(['currency' => $unit]);
        unset($this->hall);
        $this->success("Pricing currency updated to {$unit}.");
    }

    public function selectSectionSwatch(string $hexColor): void
    {
        $this->sectionColorUpdate = $hexColor;
        $this->updateSectionColor();
    }

    public function updateSectionColor(): void
    {
        $this->validate([
            'priceUpdateSection' => ['required', Rule::in(array_column(SeatSection::cases(), 'value'))],
            'sectionColorUpdate' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'sectionColorUpdate.regex' => 'Please enter a valid 6-digit hex color (e.g. #eab308).',
        ]);

        $section = SeatSection::from($this->priceUpdateSection);
        $hall = $this->hall;
        $colors = is_array($hall->section_colors) ? $hall->section_colors : SeatSection::defaultColorsMap();
        $colors[$section->value] = $this->sectionColorUpdate;

        $hall->update([
            'section_colors' => $colors,
        ]);

        Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('section', $section->value)
            ->update(['color' => $this->sectionColorUpdate]);

        if ($this->brushSection === $section->value) {
            $this->brushColor = $this->sectionColorUpdate;
        }

        if ($this->editingSeatId) {
            $this->selectSeatForEdit($this->editingSeatId);
        }

        unset($this->hall, $this->seats, $this->seatGrid);

        $this->success("Updated color for {$section->label()} to {$this->sectionColorUpdate}.");
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

    public function saveHallSettings(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'stageLabel' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'max:20'],
            'rows' => ['required', 'integer', 'min:1', 'max:25'],
            'columns' => ['required', 'integer', 'min:1', 'max:25'],
        ], [
            'name.required' => 'Hall name is required.',
            'currency.required' => 'Please specify a pricing currency or unit (e.g. USD, EUR, $).',
            'rows.min' => 'Rows must be at least 1.',
            'rows.max' => 'Maximum number of rows is 25.',
            'columns.min' => 'Columns must be at least 1.',
            'columns.max' => 'Maximum number of columns is 25.',
        ]);

        $hall = $this->hall;

        // Remove any seats outside the new grid dimensions
        Seat::query()
            ->where('hall_id', $hall->id)
            ->where(function ($query) use ($validated): void {
                $query->where('row', '>', $validated['rows'])
                    ->orWhere('col', '>', $validated['columns']);
            })
            ->delete();

        $hall->update([
            'name' => $validated['name'],
            'stage_label' => $validated['stageLabel'],
            'description' => $validated['description'],
            'currency' => trim($validated['currency']),
            'rows' => $validated['rows'],
            'columns' => $validated['columns'],
        ]);

        unset($this->hall, $this->seats, $this->seatGrid);

        $this->success('Hall settings, dimensions, and currency updated successfully.');
    }

    public function handleCellClick(int $row, int $col): void
    {
        $existingSeat = Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('row', $row)
            ->where('col', $col)
            ->first();

        if ($this->toolMode === 'erase') {
            if ($existingSeat) {
                $seatNo = $existingSeat->seat_number;
                if ($this->editingSeatId === $existingSeat->id) {
                    $this->editingSeatId = null;
                }
                $existingSeat->delete();
                unset($this->seats, $this->seatGrid);
                $this->info("Seat {$seatNo} removed (converted to aisle space).");
            }

            return;
        }

        if ($this->toolMode === 'edit') {
            if ($existingSeat) {
                $this->selectSeatForEdit($existingSeat->id);
            } else {
                $newSeat = $this->createSeatAt($row, $col);
                $this->selectSeatForEdit($newSeat->id);
                $this->success("Seat {$newSeat->seat_number} created and selected for editing.");
            }

            return;
        }

        // Default: 'paint' mode
        $section = SeatSection::tryFrom($this->brushSection) ?? SeatSection::Center;
        $price = max(0, $this->brushPrice);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $this->brushColor)
            ? $this->brushColor
            : $this->hall->getSectionColor($section);

        if ($existingSeat) {
            $existingSeat->update([
                'section' => $section,
                'color' => $color,
                'price' => $price,
            ]);
            $this->selectSeatForEdit($existingSeat->id);
            unset($this->seats, $this->seatGrid);
            $this->success("Seat {$existingSeat->seat_number} updated to {$section->shortLabel()} ({$this->hall->formatPrice($price)}).");
        } else {
            $newSeat = $this->createSeatAt($row, $col);
            $this->selectSeatForEdit($newSeat->id);
            unset($this->seats, $this->seatGrid);
            $this->success("Seat {$newSeat->seat_number} added at Row {$row}, Col {$col}.");
        }
    }

    private function createSeatAt(int $row, int $col): Seat
    {
        $section = SeatSection::tryFrom($this->brushSection) ?? SeatSection::Center;
        $prefix = trim($this->brushPrefix) !== '' ? trim($this->brushPrefix) : $section->prefix();
        $price = max(0, $this->brushPrice);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $this->brushColor)
            ? $this->brushColor
            : $this->hall->getSectionColor($section);

        $seatNumber = $this->generateUniqueSeatNumber($prefix);

        return Seat::create([
            'hall_id' => $this->hallId,
            'row' => $row,
            'col' => $col,
            'seat_number' => $seatNumber,
            'section' => $section,
            'color' => $color,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    private function generateUniqueSeatNumber(string $prefix): string
    {
        $counter = Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('seat_number', 'like', $prefix.'-%')
            ->count() + 1;

        while (
            Seat::query()
                ->where('hall_id', $this->hallId)
                ->where('seat_number', $prefix.'-'.$counter)
                ->exists()
        ) {
            $counter++;
        }

        return $prefix.'-'.$counter;
    }

    public function selectSeatForEdit(int $seatId): void
    {
        $seat = Seat::query()
            ->where('hall_id', $this->hallId)
            ->find($seatId);

        if (! $seat) {
            return;
        }

        $this->editingSeatId = $seat->id;
        $this->editSeatNumber = $seat->seat_number;
        $this->editSeatSection = $seat->section->value;
        $this->editSeatColor = $this->hall->getSeatColor($seat);
        $this->editSeatPrice = $seat->price;
        $this->editSeatActive = $seat->is_active;
    }

    public function saveEditedSeat(): void
    {
        if (! $this->editingSeatId) {
            return;
        }

        $seat = Seat::query()
            ->where('hall_id', $this->hallId)
            ->findOrFail($this->editingSeatId);

        $validated = $this->validate([
            'editSeatNumber' => [
                'required',
                'string',
                'max:30',
                Rule::unique('seats', 'seat_number')
                    ->where('hall_id', $this->hallId)
                    ->ignore($seat->id),
            ],
            'editSeatSection' => ['required', Rule::in(array_column(SeatSection::cases(), 'value'))],
            'editSeatColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'editSeatPrice' => ['required', 'integer', 'min:0'],
            'editSeatActive' => ['boolean'],
        ], [
            'editSeatNumber.required' => 'Seat number is required.',
            'editSeatNumber.unique' => 'This seat number is already in use in this hall.',
            'editSeatColor.regex' => 'Please select a valid hex color.',
            'editSeatPrice.min' => 'Seat price cannot be negative.',
        ]);

        $seat->update([
            'seat_number' => trim($validated['editSeatNumber']),
            'section' => $validated['editSeatSection'],
            'color' => $validated['editSeatColor'],
            'price' => $validated['editSeatPrice'],
            'is_active' => $validated['editSeatActive'],
        ]);

        unset($this->seats, $this->seatGrid);

        $this->success("Seat {$seat->seat_number} updated successfully.");
    }

    public function deleteEditedSeat(): void
    {
        if (! $this->editingSeatId) {
            return;
        }

        $seat = Seat::query()
            ->where('hall_id', $this->hallId)
            ->find($this->editingSeatId);

        if ($seat) {
            $seatNo = $seat->seat_number;
            $seat->delete();
            $this->editingSeatId = null;
            unset($this->seats, $this->seatGrid);
            $this->info("Seat {$seatNo} deleted from the hall layout.");
        }
    }

    public function fillRow(): void
    {
        $row = max(1, min($this->hall->rows, $this->bulkTargetRow));
        $section = SeatSection::tryFrom($this->brushSection) ?? SeatSection::Center;
        $prefix = trim($this->brushPrefix) !== '' ? trim($this->brushPrefix) : $section->prefix();
        $price = max(0, $this->brushPrice);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $this->brushColor)
            ? $this->brushColor
            : $this->hall->getSectionColor($section);

        for ($col = 1; $col <= $this->hall->columns; $col++) {
            $seat = Seat::query()
                ->where('hall_id', $this->hallId)
                ->where('row', $row)
                ->where('col', $col)
                ->first();

            if ($seat) {
                $seat->update([
                    'section' => $section,
                    'color' => $color,
                    'price' => $price,
                ]);
            } else {
                Seat::create([
                    'hall_id' => $this->hallId,
                    'row' => $row,
                    'col' => $col,
                    'seat_number' => $this->generateUniqueSeatNumber($prefix),
                    'section' => $section,
                    'color' => $color,
                    'price' => $price,
                    'is_active' => true,
                ]);
            }
        }

        unset($this->seats, $this->seatGrid);
        $this->success("Row {$row} populated with {$section->shortLabel()} seats ({$this->hall->formatPrice($price)}).");
    }

    public function clearRow(): void
    {
        $row = max(1, min($this->hall->rows, $this->bulkTargetRow));

        Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('row', $row)
            ->delete();

        $this->editingSeatId = null;
        unset($this->seats, $this->seatGrid);
        $this->info("Row {$row} cleared into an aisle.");
    }

    public function fillColumn(): void
    {
        $col = max(1, min($this->hall->columns, $this->bulkTargetCol));
        $section = SeatSection::tryFrom($this->brushSection) ?? SeatSection::Left;
        $prefix = trim($this->brushPrefix) !== '' ? trim($this->brushPrefix) : $section->prefix();
        $price = max(0, $this->brushPrice);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $this->brushColor)
            ? $this->brushColor
            : $this->hall->getSectionColor($section);

        for ($row = 1; $row <= $this->hall->rows; $row++) {
            $seat = Seat::query()
                ->where('hall_id', $this->hallId)
                ->where('row', $row)
                ->where('col', $col)
                ->first();

            if ($seat) {
                $seat->update([
                    'section' => $section,
                    'color' => $color,
                    'price' => $price,
                ]);
            } else {
                Seat::create([
                    'hall_id' => $this->hallId,
                    'row' => $row,
                    'col' => $col,
                    'seat_number' => $this->generateUniqueSeatNumber($prefix),
                    'section' => $section,
                    'color' => $color,
                    'price' => $price,
                    'is_active' => true,
                ]);
            }
        }

        unset($this->seats, $this->seatGrid);
        $this->success("Column {$col} populated with {$section->shortLabel()} seats ({$this->hall->formatPrice($price)}).");
    }

    public function clearColumn(): void
    {
        $col = max(1, min($this->hall->columns, $this->bulkTargetCol));

        Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('col', $col)
            ->delete();

        $this->editingSeatId = null;
        unset($this->seats, $this->seatGrid);
        $this->info("Column {$col} cleared into a vertical aisle.");
    }

    public function updateSectionPrice(): void
    {
        $this->validate([
            'priceUpdateSection' => ['required', Rule::in(array_column(SeatSection::cases(), 'value'))],
            'priceUpdateAmount' => ['required', 'integer', 'min:0'],
            'sectionColorUpdate' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $section = SeatSection::from($this->priceUpdateSection);

        $hall = $this->hall;
        $colors = is_array($hall->section_colors) ? $hall->section_colors : SeatSection::defaultColorsMap();
        $colors[$section->value] = $this->sectionColorUpdate;
        $hall->update(['section_colors' => $colors]);

        $updatedCount = Seat::query()
            ->where('hall_id', $this->hallId)
            ->where('section', $section->value)
            ->update([
                'price' => $this->priceUpdateAmount,
                'color' => $this->sectionColorUpdate,
            ]);

        if ($this->brushSection === $section->value) {
            $this->brushColor = $this->sectionColorUpdate;
        }

        if ($this->editingSeatId) {
            $this->selectSeatForEdit($this->editingSeatId);
        }

        unset($this->hall, $this->seats, $this->seatGrid);

        $this->success("Updated {$updatedCount} seats in {$section->label()} to {$this->hall->formatPrice($this->priceUpdateAmount)} and color {$this->sectionColorUpdate}.");
    }

    public function renumberAllSeats(): void
    {
        $seats = Seat::query()
            ->where('hall_id', $this->hallId)
            ->orderBy('row')
            ->orderBy('col')
            ->get();

        // Assign temporary unique numbers first to avoid unique constraint collisions during renumbering
        foreach ($seats as $seat) {
            $seat->update(['seat_number' => 'TMP-'.$seat->id]);
        }

        $counters = [];

        foreach ($seats as $seat) {
            $prefix = $seat->section->prefix();
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
            $seat->update([
                'seat_number' => $prefix.'-'.$counters[$prefix],
            ]);
        }

        if ($this->editingSeatId) {
            $this->selectSeatForEdit($this->editingSeatId);
        }

        unset($this->seats, $this->seatGrid);
        $this->success('All seat numbers have been sequentially renumbered by section and row/column order.');
    }

    public function applyPreset(string $preset): void
    {
        $hall = $this->hall;
        HallLayoutPresets::apply($hall, $preset);

        $hall->refresh();
        $this->activePreset = $preset;
        $this->syncHallFields($hall);
        $this->editingSeatId = null;
        unset($this->hall, $this->seats, $this->seatGrid);

        $presetName = match ($preset) {
            'u_shape' => 'U-Shape Conference Layout (Front, Left/Right Wings, Back)',
            'cinema' => 'Classic Cinema Layout (Front, Center, Back)',
            default => 'Concert & Theater Layout (VIP Front, Front, Side Wings, Center, Back)',
        };

        $this->success("Applied preset: {$presetName}.");
    }
};
?>

<div class="space-y-5">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 border border-zinc-200">
        <div class="space-y-0.5">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider bg-zinc-900 text-white">
                    Admin Studio
                </span>
                <h1 class="text-lg font-bold tracking-tight text-zinc-900">Hall Layout, Colors & Seat Pricing Designer</h1>
            </div>
            <p class="text-xs text-zinc-500">
                Full-width layout studio: configure templates & brush tools above, click circular seats on the map, and adjust settings below.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('home') }}"
                wire:navigate
                class="h-8 px-3 inline-flex items-center text-xs font-medium bg-white hover:bg-zinc-100 text-zinc-900 border border-zinc-300 transition"
            >
                Preview User Booking Map
            </a>
            <a
                href="{{ route('admin.reservations') }}"
                wire:navigate
                class="h-8 px-3 inline-flex items-center text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white transition"
            >
                Manage All Reservations
            </a>
        </div>
    </div>

    {{-- TOP CONTROLS (Above Full-Width Hall Map): Presets + Brush/Tool Bar --}}
    <div class="bg-white p-4 border border-zinc-200 space-y-4">
        {{-- Row 1: Preset Templates --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-3 border-b border-zinc-200">
            <div class="text-xs font-bold uppercase tracking-wider text-zinc-900">
                Preset Hall Layouts:
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="applyPreset('concert')"
                    class="h-8 px-3 border text-xs font-medium transition cursor-pointer {{ $activePreset === 'concert' ? 'bg-zinc-900 text-white border-zinc-900 font-semibold' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border-zinc-300' }}"
                >
                    Concert Layout (Front, Wings, Center, Back)
                </button>

                <button
                    type="button"
                    wire:click="applyPreset('u_shape')"
                    class="h-8 px-3 border text-xs font-medium transition cursor-pointer {{ $activePreset === 'u_shape' ? 'bg-zinc-900 text-white border-zinc-900 font-semibold' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border-zinc-300' }}"
                >
                    U-Shape Layout (Front & Side Wings)
                </button>

                <button
                    type="button"
                    wire:click="applyPreset('cinema')"
                    class="h-8 px-3 border text-xs font-medium transition cursor-pointer {{ $activePreset === 'cinema' ? 'bg-zinc-900 text-white border-zinc-900 font-semibold' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border-zinc-300' }}"
                >
                    Cinema Layout (Front, Center, Back)
                </button>

                <button
                    type="button"
                    wire:click="renumberAllSeats"
                    class="h-8 px-3 bg-white hover:bg-zinc-100 text-zinc-800 border border-zinc-300 text-xs font-medium transition cursor-pointer"
                >
                    Auto-Renumber Seats
                </button>
            </div>
        </div>

        {{-- Row 2: Tool Mode + Active Brush Controls + Quick Row/Col Operations --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
            {{-- Tool Mode Selector --}}
            <div class="lg:col-span-3 space-y-1">
                <label class="block text-xs font-semibold text-zinc-800">1. Click Mode on Map</label>
                <div class="grid grid-cols-3 gap-1">
                    <button
                        type="button"
                        wire:click="$set('toolMode', 'paint')"
                        class="h-9 px-2 border text-xs font-medium transition cursor-pointer {{ $toolMode === 'paint' ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-zinc-50 text-zinc-700 border-zinc-300 hover:bg-zinc-100' }}"
                    >
                        Paint
                    </button>
                    <button
                        type="button"
                        wire:click="$set('toolMode', 'edit')"
                        class="h-9 px-2 border text-xs font-medium transition cursor-pointer {{ $toolMode === 'edit' ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-zinc-50 text-zinc-700 border-zinc-300 hover:bg-zinc-100' }}"
                    >
                        Inspect
                    </button>
                    <button
                        type="button"
                        wire:click="$set('toolMode', 'erase')"
                        class="h-9 px-2 border text-xs font-medium transition cursor-pointer {{ $toolMode === 'erase' ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-zinc-50 text-zinc-700 border-zinc-300 hover:bg-zinc-100' }}"
                    >
                        Erase
                    </button>
                </div>
            </div>

            @if($toolMode === 'paint')
                {{-- Brush Section, Price, Prefix, Color --}}
                <div class="lg:col-span-5 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Section</label>
                        <select
                            wire:model.live="brushSection"
                            class="w-full h-9 px-2 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        >
                            @foreach(SeatSection::cases() as $section)
                                <option value="{{ $section->value }}">{{ $section->shortLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Price ({{ $this->hall->currency }})</label>
                        <input
                            type="number"
                            wire:model="brushPrice"
                            step="5"
                            class="w-full h-9 px-2.5 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Prefix</label>
                        <input
                            type="text"
                            wire:model="brushPrefix"
                            placeholder="VIP, F..."
                            class="w-full h-9 px-2.5 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Brush Color</label>
                        <div class="flex items-center gap-1">
                            <input
                                type="color"
                                wire:model.live="brushColor"
                                class="w-9 h-9 p-0.5 bg-white border border-zinc-300 cursor-pointer shrink-0"
                            />
                            <input
                                type="text"
                                wire:model.live="brushColor"
                                class="w-full h-9 px-1.5 text-[11px] font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                            />
                        </div>
                    </div>
                </div>

                {{-- Quick Row / Col Actions --}}
                <div class="lg:col-span-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Row Action (1-{{ $this->hall->rows }})</label>
                        <div class="flex items-center gap-1">
                            <input
                                type="number"
                                wire:model="bulkTargetRow"
                                min="1"
                                max="{{ $this->hall->rows }}"
                                class="w-12 h-9 px-1.5 text-xs text-center bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                            />
                            <button
                                type="button"
                                wire:click="fillRow"
                                class="h-9 px-2.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium grow cursor-pointer"
                            >
                                Fill Row
                            </button>
                            <button
                                type="button"
                                wire:click="clearRow"
                                class="h-9 px-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 border border-zinc-300 text-xs font-medium cursor-pointer"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Col Action (1-{{ $this->hall->columns }})</label>
                        <div class="flex items-center gap-1">
                            <input
                                type="number"
                                wire:model="bulkTargetCol"
                                min="1"
                                max="{{ $this->hall->columns }}"
                                class="w-12 h-9 px-1.5 text-xs text-center bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                            />
                            <button
                                type="button"
                                wire:click="fillColumn"
                                class="h-9 px-2.5 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium grow cursor-pointer"
                            >
                                Fill Col
                            </button>
                            <button
                                type="button"
                                wire:click="clearColumn"
                                class="h-9 px-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 border border-zinc-300 text-xs font-medium cursor-pointer"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            @elseif($toolMode === 'erase')
                <div class="lg:col-span-9 bg-zinc-100 border border-zinc-300 p-2.5 text-xs text-zinc-700">
                    <strong>Erase Mode Active:</strong> Click any circular seat on the map below to remove it and convert it into an aisle space.
                </div>
            @else
                <div class="lg:col-span-9 bg-zinc-100 border border-zinc-300 p-2.5 text-xs text-zinc-700">
                    <strong>Inspect / Edit Mode Active:</strong> Click any circular seat on the map below to view and edit its exact seat number, section, price, and color.
                </div>
            @endif
        </div>
    </div>

    {{-- SELECTED SEAT INSPECTOR BAR (Shown Above Map When a Seat is Clicked) --}}
    @if($editingSeatId)
        <div class="bg-white p-4 border-2 border-zinc-900">
            <form wire:submit="saveEditedSeat" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-zinc-900">Selected Seat Number</label>
                    <input
                        type="text"
                        wire:model="editSeatNumber"
                        class="w-full h-9 px-3 text-xs font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                    />
                    @error('editSeatNumber')
                        <p class="text-[11px] text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-medium text-zinc-700">Section / Type</label>
                    <select
                        wire:model.live="editSeatSection"
                        class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                    >
                        @foreach(SeatSection::cases() as $section)
                            <option value="{{ $section->value }}">{{ $section->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-medium text-zinc-700">Seat Price ({{ $this->hall->currency }})</label>
                    <input
                        type="number"
                        wire:model="editSeatPrice"
                        step="1"
                        class="w-full h-9 px-3 text-xs font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                    />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-medium text-zinc-700">Seat Color</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="color"
                            wire:model.live="editSeatColor"
                            class="w-9 h-9 p-0.5 bg-white border border-zinc-300 cursor-pointer shrink-0"
                        />
                        <input
                            type="text"
                            wire:model.live="editSeatColor"
                            class="w-full h-9 px-2 text-xs font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2 h-9">
                    <input
                        id="editSeatActive"
                        type="checkbox"
                        wire:model="editSeatActive"
                        class="w-4 h-4 border-zinc-300 text-zinc-900 focus:ring-zinc-900"
                    />
                    <label for="editSeatActive" class="text-xs font-medium text-zinc-700">Active / Bookable</label>
                </div>

                <div class="flex items-center gap-1.5">
                    <button
                        type="submit"
                        class="h-9 px-3 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium grow cursor-pointer"
                    >
                        Save Seat
                    </button>
                    <button
                        type="button"
                        wire:click="deleteEditedSeat"
                        class="h-9 px-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 text-xs font-medium cursor-pointer"
                    >
                        Delete
                    </button>
                    <button
                        type="button"
                        wire:click="$set('editingSeatId', null)"
                        class="h-9 px-2.5 bg-white hover:bg-zinc-100 text-zinc-600 border border-zinc-300 text-xs cursor-pointer"
                        title="Close inspector"
                    >
                        ✕
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- FULL-WIDTH INTERACTIVE HALL LAYOUT CANVAS --}}
    <div class="w-full bg-white border border-zinc-200 p-4 sm:p-5 space-y-4">
        {{-- Top Bar: Seat Types, Colors & Prices Clearly Visible --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-zinc-200 pb-3">
            <div>
                <h2 class="font-bold text-sm text-zinc-900">
                    Full-Width Hall Map ({{ $this->hall->rows }} Rows × {{ $this->hall->columns }} Columns)
                </h2>
                <p class="text-[11px] text-zinc-500">
                    Seat types and prices are shown on the right. Click any circle below to place, inspect, or erase a seat.
                </p>
            </div>

            {{-- Section Type + Color Circle + Price Legend at the top --}}
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @foreach(SeatSection::cases() as $section)
                    @php
                        $secSeats = $this->seats->where('section', $section);
                        $secPrice = $secSeats->first()?->price ?? $section->defaultPrice();
                        $secColor = $this->hall->getSectionColor($section);
                    @endphp
                    <div
                        style="{{ $this->hall->getSectionBadgeStyle($section) }}"
                        class="px-2.5 py-1 border flex items-center gap-1.5 text-xs font-medium"
                    >
                        <span class="w-3 h-3 rounded-full inline-block border border-black/20" style="background-color: {{ $secColor }};"></span>
                        <span class="font-bold">{{ $section->shortLabel() }}</span>
                        <span class="font-mono font-semibold">{{ $this->hall->formatCompactPrice((int) $secPrice) }}</span>
                        <span class="opacity-70 text-[10px]">({{ $secSeats->count() }})</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Compact Stage Indicator --}}
        <div class="max-w-md mx-auto">
            <div class="w-full py-1.5 px-4 bg-zinc-900 text-white border-b-2 border-zinc-500 text-center">
                <div class="text-xs font-bold uppercase tracking-widest">
                    {{ $this->hall->stage_label }}
                </div>
            </div>
        </div>

        {{-- Compact 2D Circular Seat Grid --}}
        <div class="overflow-x-auto py-1">
            <div class="min-w-fit mx-auto flex flex-col gap-1.5 items-center">
                {{-- Column numbers header --}}
                <div class="flex items-center gap-1.5">
                    <div class="w-10 shrink-0"></div>
                    @for($col = 1; $col <= $this->hall->columns; $col++)
                        <div class="w-8 sm:w-9 text-center text-[10px] font-mono text-zinc-400">
                            C{{ $col }}
                        </div>
                    @endfor
                    <div class="w-10 shrink-0"></div>
                </div>

                @for($row = 1; $row <= $this->hall->rows; $row++)
                    <div class="flex items-center gap-1.5" wire:key="admin-row-{{ $row }}">
                        <div class="w-10 text-right pr-1.5 text-[10px] font-mono font-semibold text-zinc-400 shrink-0">
                            R{{ $row }}
                        </div>

                        @for($col = 1; $col <= $this->hall->columns; $col++)
                            @php
                                $seat = $this->seatGrid[$row.'-'.$col] ?? null;
                                $isBeingEdited = $seat && $this->editingSeatId === $seat->id;
                            @endphp

                            @if($seat)
                                <button
                                    type="button"
                                    wire:key="admin-seat-{{ $row }}-{{ $col }}-{{ $seat->id }}"
                                    wire:click="handleCellClick({{ $row }}, {{ $col }})"
                                    title="{{ $seat->seat_number }} | {{ $seat->section->label() }} | Price: {{ $this->hall->formatPrice($seat->price) }}"
                                    style="{{ $this->hall->getSeatStyle($seat) }}"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full border-2 flex items-center justify-center p-0.5 transition hover:brightness-95 cursor-pointer relative
                                        {{ ! $seat->is_active ? 'opacity-40 border-dashed' : '' }}
                                        {{ $isBeingEdited ? 'ring-2 ring-zinc-900 ring-offset-1 scale-110 z-10' : '' }}
                                    "
                                >
                                    @if($seat->isReserved())
                                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-zinc-900 border border-white" title="Reserved"></span>
                                    @endif

                                    <span class="text-[9px] font-bold leading-none truncate max-w-full font-mono">
                                        {{ $seat->seat_number }}
                                    </span>
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:key="admin-empty-{{ $row }}-{{ $col }}"
                                    wire:click="handleCellClick({{ $row }}, {{ $col }})"
                                    title="Row {{ $row }}, Col {{ $col }} (Aisle) - Click to place seat"
                                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full border border-dashed border-zinc-200 hover:border-zinc-900 hover:bg-zinc-100 flex items-center justify-center text-[10px] text-zinc-300 hover:text-zinc-900 transition cursor-pointer"
                                >
                                    +
                                </button>
                            @endif
                        @endfor

                        <div class="w-10 text-left pl-1.5 text-[10px] font-mono font-semibold text-zinc-400 shrink-0">
                            R{{ $row }}
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <div class="text-center">
            <span class="inline-block px-3 py-0.5 bg-zinc-100 border border-zinc-200 text-[10px] font-medium text-zinc-500 uppercase tracking-wider">
                Rear of Hall (Back Rows)
            </span>
        </div>
    </div>

    {{-- BOTTOM PANELS (Below Full-Width Hall Map): Section Colors/Pricing/Currency + Hall Settings --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- Panel A: Section Colors, Bulk Pricing & Currency --}}
        <div class="bg-white p-5 border border-zinc-200 space-y-4">
            <h2 class="font-bold text-sm text-zinc-900 border-b border-zinc-200 pb-2.5">
                2. Section Colors, Pricing & Currency
            </h2>

            {{-- Quick Currency Unit Selector --}}
            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-700">Active Pricing Currency / Unit</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(['USD', 'EUR', 'GBP', 'CAD', 'AED', 'IRR'] as $unit)
                        <button
                            type="button"
                            wire:click="setQuickCurrency('{{ $unit }}')"
                            class="h-7 px-2.5 text-xs font-mono border transition cursor-pointer {{ $this->hall->currency === $unit ? 'bg-zinc-900 text-white border-zinc-900 font-bold' : 'bg-zinc-50 text-zinc-700 border-zinc-300 hover:bg-zinc-100' }}"
                        >
                            {{ $unit }}
                        </button>
                    @endforeach
                </div>
            </div>

            <form wire:submit="updateSectionPrice" class="space-y-3 pt-2 border-t border-zinc-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Select Section</label>
                        <select
                            wire:model.live="priceUpdateSection"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        >
                            @foreach(SeatSection::cases() as $section)
                                <option value="{{ $section->value }}">{{ $section->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Section Price ({{ $this->hall->currency }})</label>
                        <input
                            type="number"
                            wire:model="priceUpdateAmount"
                            step="5"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>
                </div>

                {{-- Section Color Picker & Swatches --}}
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-zinc-700">Section Seat Color</label>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            wire:model.live="sectionColorUpdate"
                            wire:change="updateSectionColor"
                            class="w-9 h-9 p-0.5 bg-white border border-zinc-300 cursor-pointer shrink-0"
                        />
                        <input
                            type="text"
                            wire:model.live="sectionColorUpdate"
                            placeholder="#eab308"
                            class="w-full h-9 px-3 text-xs font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                        <button
                            type="button"
                            wire:click="updateSectionColor"
                            class="h-9 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 text-xs font-medium shrink-0 cursor-pointer"
                        >
                            Set Color
                        </button>
                    </div>

                    {{-- Quick Color Swatches --}}
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        @foreach(SeatSection::colorPalette() as $colorName => $hex)
                            <button
                                type="button"
                                wire:click="selectSectionSwatch('{{ $hex }}')"
                                title="{{ $colorName }} ({{ $hex }})"
                                style="background-color: {{ $hex }};"
                                class="w-6 h-6 rounded-full border cursor-pointer transition {{ strtolower($sectionColorUpdate) === strtolower($hex) ? 'ring-2 ring-zinc-900 ring-offset-1 border-white scale-110' : 'border-zinc-300 opacity-85 hover:opacity-100' }}"
                            ></button>
                        @endforeach
                    </div>
                    @error('sectionColorUpdate')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full h-9 px-4 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium transition cursor-pointer"
                >
                    Apply Price & Color to Section
                </button>
            </form>
        </div>

        {{-- Panel B: Hall Dimensions, Currency & General Settings --}}
        <div class="bg-white p-5 border border-zinc-200 space-y-4">
            <h2 class="font-bold text-sm text-zinc-900 border-b border-zinc-200 pb-2.5">
                3. Hall Settings, Custom Currency & Grid Size
            </h2>

            <form wire:submit="saveHallSettings" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Hall Name</label>
                        <input
                            type="text"
                            wire:model="name"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Custom Currency Unit (USD, $, EUR...)</label>
                        <input
                            type="text"
                            wire:model="currency"
                            placeholder="USD"
                            class="w-full h-9 px-3 text-xs font-mono bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                        @error('currency')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Stage Label (Front of Hall)</label>
                        <input
                            type="text"
                            wire:model="stageLabel"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Description</label>
                        <input
                            type="text"
                            wire:model="description"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Rows (1-25)</label>
                        <input
                            type="number"
                            wire:model="rows"
                            min="1"
                            max="25"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-zinc-700">Columns (1-25)</label>
                        <input
                            type="number"
                            wire:model="columns"
                            min="1"
                            max="25"
                            class="w-full h-9 px-3 text-xs bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full h-9 px-4 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium transition cursor-pointer"
                >
                    Save Hall Settings & Dimensions
                </button>
            </form>
        </div>
    </div>
</div>
