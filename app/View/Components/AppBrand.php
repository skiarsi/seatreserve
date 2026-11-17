<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AppBrand extends Component
{
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <a href="/" wire:navigate>
                    <!-- Hidden when collapsed -->
                    <div {{ $attributes->class(["hidden-when-collapsed"]) }}>
                        <div class="flex items-center gap-2.5 w-fit">
                            <div class="w-8 h-8 bg-zinc-900 text-white flex items-center justify-center rounded-md">
                                <x-icon name="o-ticket" class="w-4 h-4" />
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-base tracking-tight text-zinc-900 leading-none">
                                    SeatReserve
                                </span>
                                <span class="text-[11px] text-zinc-500 mt-0.5">Hall Seating & Booking</span>
                            </div>
                        </div>
                    </div>

                    <!-- Display when collapsed -->
                    <div class="display-when-collapsed hidden mx-5 mt-5 mb-1 h-[28px]">
                        <x-icon name="s-ticket" class="w-6 -mb-1.5 text-zinc-900" />
                    </div>
                </a>
            HTML;
    }
}
