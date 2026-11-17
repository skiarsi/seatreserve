<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Sign In')] class extends Component
{
    use Toast;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ]);

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->addError('email', 'Invalid email or password.');

            return;
        }

        session()->regenerate();

        $user = Auth::user();

        $this->success('Welcome back, '.$user?->name);

        if ($user instanceof User && $user->isAdmin()) {
            $this->redirectRoute('admin.layout', navigate: true);

            return;
        }

        $this->redirectRoute('home', navigate: true);
    }

    public function quickLogin(string $role): void
    {
        $this->email = $role === 'admin' ? 'admin@example.com' : 'user@example.com';
        $this->password = 'password';
        $this->login();
    }
};
?>

<div class="max-w-md mx-auto mt-8">
    <div class="bg-white border border-zinc-200 shadow-xs p-6 space-y-6">
        <div class="space-y-1">
            <h1 class="text-xl font-bold tracking-tight text-zinc-900">Sign in to your account</h1>
            <p class="text-xs text-zinc-500">Enter your credentials below to reserve seats or manage the hall layout.</p>
        </div>

        <form wire:submit="login" class="space-y-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-medium text-zinc-800">Email Address</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="admin@example.com"
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
                    placeholder="••••••••"
                    class="w-full h-9 px-3 text-sm bg-white border border-zinc-300 rounded-none focus:outline-none focus:border-zinc-900"
                />
                @error('password')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    type="checkbox"
                    wire:model="remember"
                    class="w-4 h-4 border-zinc-300 text-zinc-900 focus:ring-zinc-900"
                />
                <label for="remember" class="text-xs text-zinc-600">Remember me</label>
            </div>

            <button
                type="submit"
                class="w-full h-9 px-4 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-medium transition cursor-pointer flex items-center justify-center"
            >
                <span wire:loading.remove wire:target="login">Sign In</span>
                <span wire:loading wire:target="login">Signing in...</span>
            </button>
        </form>

        <div class="relative py-1">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-zinc-200"></div>
            </div>
            <div class="relative flex justify-center text-[11px] uppercase">
                <span class="bg-white px-2 text-zinc-400">Quick Demo Access</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5">
            <button
                type="button"
                wire:click="quickLogin('admin')"
                class="h-9 px-3 bg-zinc-100 hover:bg-zinc-200 text-zinc-900 border border-zinc-300 text-xs font-medium transition cursor-pointer"
            >
                Login as Admin
            </button>

            <button
                type="button"
                wire:click="quickLogin('user')"
                class="h-9 px-3 bg-white hover:bg-zinc-100 text-zinc-800 border border-zinc-300 text-xs font-medium transition cursor-pointer"
            >
                Login as User
            </button>
        </div>

        <div class="bg-zinc-50 border border-zinc-200 p-3 text-xs space-y-1 text-zinc-600">
            <div class="font-semibold text-zinc-900">Default Credentials:</div>
            <div class="flex justify-between font-mono text-[11px]">
                <span>Admin:</span>
                <span>admin@example.com / password</span>
            </div>
            <div class="flex justify-between font-mono text-[11px]">
                <span>User:</span>
                <span>user@example.com / password</span>
            </div>
        </div>

        <div class="text-center text-xs text-zinc-500">
            Don't have an account?
            <a href="{{ route('register') }}" wire:navigate class="font-semibold text-zinc-900 underline underline-offset-4">Create an account</a>
        </div>
    </div>
</div>
