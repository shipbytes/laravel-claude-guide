@props(['action', 'placeholder'])
<form method="post" action="{{ $action }}" class="rounded-md border border-zinc-200 bg-zinc-50 p-4">
    @csrf
    <label for="question" class="block text-sm font-medium">Ask about your invoices and clients</label>
    <textarea id="question" name="question" rows="2" placeholder="{{ $placeholder }}" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2">{{ old('question') }}</textarea>
    @error('question') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    <div class="mt-2 flex items-center gap-3">
        <button type="submit" class="rounded-md bg-zinc-900 px-3 py-1.5 text-sm text-white">Ask</button>
        @if (session('assistant_error')) <p class="text-sm text-red-700" role="alert">{{ session('assistant_error') }}</p> @endif
    </div>
</form>
