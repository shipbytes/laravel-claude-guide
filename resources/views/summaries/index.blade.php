<x-layouts.app title="Monthly summaries">
    <h1 class="mb-6 text-xl font-semibold">Monthly summaries</h1>
    @forelse ($summaries as $summary)
        <section class="mb-6 rounded-lg border border-zinc-200 bg-white px-5 py-4">
            <h2 class="mb-2 text-sm font-semibold">{{ $summary->month->format('F Y') }}
                @if ($summary->status !== 'ready') <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $summary->status }}</span> @endif
            </h2>
            @if ($summary->summary)
                <div class="prose prose-sm max-w-none text-sm">{!! Str::markdown($summary->summary, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
            @endif
        </section>
    @empty
        <p class="text-sm text-zinc-500">No summaries yet. They are written on the first of each month.</p>
    @endforelse
</x-layouts.app>
