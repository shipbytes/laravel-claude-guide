<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SummariesController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('summaries.index', [
            'summaries' => $request->user()->company->summaries()->orderByDesc('month')->get(),
        ]);
    }
}
