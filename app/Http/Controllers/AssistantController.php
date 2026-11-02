<?php

namespace App\Http\Controllers;

use App\AI\Assistant;
use App\AI\AssistantFailed;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// /assistant: a list of past conversations and a box for a new question; /assistant/{id}:
// one conversation with its follow-up box. Each question is a request that waits for the
// answer; the assistant is not streamed, because tool calls happen between the words.
class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        return view('assistant.index', [
            'conversations' => $request->user()->company->conversations()->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request, Assistant $assistant): RedirectResponse
    {
        Gate::authorize('create', Conversation::class);
        $question = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']])['question'];

        $conversation = new Conversation(['messages' => []]);
        $conversation->company()->associate($request->user()->company);
        $conversation->user()->associate($request->user());

        return $this->answer($conversation, $question, $assistant, route('assistant.index'));
    }

    public function show(Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        return view('assistant.show', ['conversation' => $conversation]);
    }

    public function ask(Request $request, Conversation $conversation, Assistant $assistant): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $question = $request->validate(['question' => ['required', 'string', 'min:3', 'max:1000']])['question'];

        return $this->answer($conversation, $question, $assistant, route('assistant.show', $conversation));
    }

    private function answer(Conversation $conversation, string $question, Assistant $assistant, string $backTo): RedirectResponse
    {
        try {
            $assistant->ask($conversation, $question);
        } catch (AssistantFailed $e) {
            report($e);

            return redirect($backTo)->withInput(['question' => $question])
                ->with('assistant_error', 'The assistant could not answer. Try again in a moment.');
        }

        return redirect()->route('assistant.show', $conversation);
    }
}
