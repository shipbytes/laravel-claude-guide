<x-layouts.app title="Assistant">
    <h1 class="mb-6 text-xl font-semibold">Assistant</h1>
    <x-assistant.ask :action="route('assistant.store')" placeholder="e.g. Which invoices are overdue, and how much is outstanding in total?" />

    @if ($conversations->isNotEmpty())
        <h2 class="mt-10 mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Earlier</h2>
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white text-sm">
            @foreach ($conversations as $conversation)
                <li><a href="{{ route('assistant.show', $conversation) }}" class="block px-4 py-3 hover:bg-zinc-50">{{ $conversation->title }}
                    <span class="text-zinc-400"> · {{ $conversation->updated_at->diffForHumans() }}</span></a></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
