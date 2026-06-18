<x-layouts.guest :title="__('Log In')">
    <div>
        <h2 class="text-2xl font-bold text-ink">Welcome back</h2>
        <p class="mt-1 text-sm text-muted">Sign in to {{ config('app.name') }}</p>
    </div>
    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-muted mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="input @error('email') !border-danger @enderror" placeholder="you@megasol.com">
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-muted">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-brand hover:text-brand-strong">Forgot password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="input">
        </div>
        <div class="flex items-center">
            <input id="remember" type="checkbox" name="remember" class="w-4 h-4 rounded border-border text-brand">
            <label for="remember" class="ml-2 text-sm text-muted">Remember me</label>
        </div>
        <button type="submit" class="btn-primary w-full py-2.5">Log In</button>
    </form>
</x-layouts.guest>
