<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'InvoiceMini' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('invoices.index') }}" class="font-semibold tracking-tight">InvoiceMini</a>
            @auth
                <form method="post" action="{{ route('logout') }}" class="flex items-center gap-4 text-sm">
                    @csrf
                    <span class="text-zinc-500">{{ auth()->user()->company->name }}</span>
                    <button type="submit" class="text-zinc-600 hover:text-zinc-900">Log out</button>
                </form>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <p class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
