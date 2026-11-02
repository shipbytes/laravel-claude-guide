<x-layouts.app title="Log in">
    <div class="mx-auto max-w-sm rounded-lg border border-zinc-200 bg-white p-6">
        <h1 class="mb-4 text-lg font-semibold">Sign in to InvoiceMini</h1>
        <form method="post" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>
            <button type="submit" class="w-full rounded-md bg-zinc-900 px-3 py-2 text-white">Log in</button>
        </form>
    </div>
</x-layouts.app>
