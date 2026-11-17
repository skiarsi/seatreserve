<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - SeatReserve' : 'SeatReserve - Hall Seating & Booking' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-zinc-50 text-zinc-900">

    {{-- Top Navigation Bar --}}
    <header class="sticky top-0 z-30 bg-white border-b border-zinc-200">
        <div class="w-full px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-6">
                <x-app-brand />

                <nav class="hidden md:flex items-center gap-1 text-sm">
                    <a
                        href="{{ route('home') }}"
                        wire:navigate
                        class="px-3 py-1.5 rounded-md font-medium transition {{ request()->routeIs('home') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}"
                    >
                        Hall Map & Booking
                    </a>

                    @auth
                        <a
                            href="{{ route('reservations.mine') }}"
                            wire:navigate
                            class="px-3 py-1.5 rounded-md font-medium transition {{ request()->routeIs('reservations.mine') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}"
                        >
                            My Reservations
                        </a>

                        @if(auth()->user()->isAdmin())
                            <a
                                href="{{ route('admin.layout') }}"
                                wire:navigate
                                class="px-3 py-1.5 rounded-md font-medium transition {{ request()->routeIs('admin.layout') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}"
                            >
                                Admin: Layout & Pricing
                            </a>

                            <a
                                href="{{ route('admin.reservations') }}"
                                wire:navigate
                                class="px-3 py-1.5 rounded-md font-medium transition {{ request()->routeIs('admin.reservations') ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100' }}"
                            >
                                Admin: Reservations
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>

            <div class="flex items-center gap-3">
                @if($user = auth()->user())
                    <div class="hidden sm:flex items-center gap-2 text-xs text-zinc-600 border-r border-zinc-200 pr-3">
                        <span class="font-semibold text-zinc-900">{{ $user->name }}</span>
                        <span class="text-zinc-400">({{ $user->email }})</span>
                        @if($user->isAdmin())
                            <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-zinc-900 text-white rounded">
                                Admin
                            </span>
                        @endif
                    </div>

                    <a
                        href="{{ route('logout') }}"
                        class="px-3 py-1.5 text-xs font-medium bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 rounded-md transition"
                    >
                        Sign Out
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        wire:navigate
                        class="px-3 py-1.5 text-xs font-medium bg-white hover:bg-zinc-100 text-zinc-900 border border-zinc-300 rounded-md transition"
                    >
                        Sign In
                    </a>
                    <a
                        href="{{ route('register') }}"
                        wire:navigate
                        class="px-3 py-1.5 text-xs font-medium bg-zinc-900 hover:bg-zinc-800 text-white rounded-md transition"
                    >
                        Register
                    </a>
                @endif
            </div>
        </div>

        {{-- Mobile Secondary Nav --}}
        <div class="md:hidden border-t border-zinc-200 px-4 py-2 flex flex-wrap items-center gap-1.5 bg-zinc-50 text-xs">
            <a
                href="{{ route('home') }}"
                wire:navigate
                class="px-2.5 py-1 rounded font-medium {{ request()->routeIs('home') ? 'bg-zinc-900 text-white' : 'text-zinc-700 bg-white border border-zinc-200' }}"
            >
                Hall Map
            </a>
            @auth
                <a
                    href="{{ route('reservations.mine') }}"
                    wire:navigate
                    class="px-2.5 py-1 rounded font-medium {{ request()->routeIs('reservations.mine') ? 'bg-zinc-900 text-white' : 'text-zinc-700 bg-white border border-zinc-200' }}"
                >
                    My Reservations
                </a>
                @if(auth()->user()->isAdmin())
                    <a
                        href="{{ route('admin.layout') }}"
                        wire:navigate
                        class="px-2.5 py-1 rounded font-medium {{ request()->routeIs('admin.layout') ? 'bg-zinc-900 text-white' : 'text-zinc-700 bg-white border border-zinc-200' }}"
                    >
                        Admin Layout
                    </a>
                    <a
                        href="{{ route('admin.reservations') }}"
                        wire:navigate
                        class="px-2.5 py-1 rounded font-medium {{ request()->routeIs('admin.reservations') ? 'bg-zinc-900 text-white' : 'text-zinc-700 bg-white border border-zinc-200' }}"
                    >
                        Admin Bookings
                    </a>
                @endif
            @endauth
        </div>
    </header>

    {{-- Main Page Content --}}
    <main class="w-full px-4 sm:px-6 lg:px-8 py-5">
        {{ $slot }}
    </main>

    {{-- Toast Notifications --}}
    <x-toast />
</body>
</html>
