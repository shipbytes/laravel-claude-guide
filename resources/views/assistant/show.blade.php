<x-layouts.app :title="$conversation->title">
    <p class="mb-4 text-sm"><a href="{{ route('assistant.index') }}" class="text-zinc-500 hover:text-zinc-900">← Assistant</a></p>

    <div class="space-y-4" id="conversation">
        @foreach ($conversation->messages as $message)
            @if ($message['role'] === 'user' && is_string($message['content']))
                <div class="rounded-md bg-zinc-900 px-4 py-3 text-sm text-white" data-role="user">{{ $message['content'] }}</div>
            @elseif ($message['role'] === 'assistant')
                @foreach ($message['content'] as $block)
                    @if ($block['type'] === 'text')
                        {{-- Markdown from the model, with any HTML it contains stripped: the model's text is data, not markup. --}}
                        <div class="prose prose-sm max-w-none rounded-md border border-zinc-200 bg-white px-4 py-3" data-role="assistant">{!! Str::markdown($block['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                    @elseif ($block['type'] === 'tool_use')
                        <p class="px-4 text-xs text-zinc-500" data-role="tool">Looked up <code>{{ $block['name'] }}</code> {{ json_encode($block['input']) }}</p>
                    @endif
                @endforeach
            @endif
        @endforeach
    </div>

    <div class="mt-6">
        <x-assistant.ask :action="route('assistant.ask', $conversation)" placeholder="Follow-up question…" />
    </div>
</x-layouts.app>
