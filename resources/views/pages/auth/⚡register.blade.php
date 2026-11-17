<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Create Account')] class extends Component
{
    use Toast;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => false,
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->success('Your account has been created successfully.');
        $this->redirectRoute('home', navigate: true);
    }
};
?>

<div class="max-w-md mx-auto mt-8">
    <div class="bg-white border border-zinc-200 shadow-xs p-6 space-y-6">
        <div class="space-y-1">
            <h1 class="text-xl font-bold tracking-tight text-zinc-900">Create an account</h1>
            <p class="text-xs text-zinc-500">Sign up to select and reserve seats in the hall.</p>
        </div>

        <form wire:submit="register" class="space-y-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-800">Full Name</label>
                <input
                    type="text"
                    wire:model="name"
                    placeholder="John Doe"
                    class="w-full h-9 px-3 text-sm bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
                @error('name')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-800">Email Address</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="you@example.com"
                    class="w-full h-9 px-3 text-sm bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
                @error('email')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-800">Password</label>
                <input
                    type="password"
                    wire:model="password"
                    placeholder="Minimum 6 characters"
                    class="w-full h-9 px-3 text-sm bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
                @error('password')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-800">Confirm Password</label>
                <input
                    type="password"
                    wire:model="password_confirmation"
                    placeholder="Repeat password"
                    class="w-full h-9 px-3 text-sm bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
            </div>

            <button
                type="submit"
                class="w-full h-9 px-4 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium transition cursor-pointer flex items-center justify-center"
            >
                <span wire:loading.remove wire:target="register">Create Account</span>
                <span wire:loading wire:target="register">Creating account...</span>
            </button>
        </form>

        <div class="text-center text-xs text-zinc-500">
            Already have an account?
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-zinc-900 underline underline-offset-4">Sign in</a>
        </div>
    </div>
</div>
