<?php

namespace App\Http\Controllers;

use App\Support\Search\MinesSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The full results page behind the header search: the same scoped search, a longer list per group. A plain GET, so it works with no
 * JavaScript (the header pill's form submits here) and a result page can be linked or reloaded.
 */
class SearchController extends Controller
{
    public function index(Request $request, MinesSearch $search): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:'.(MinesSearch::MAX_LENGTH * 2)]]);
        $q = trim((string) ($validated['q'] ?? ''));
        $typed = MinesSearch::isSearchable($q);
        $user = $request->user();

        return view('search.index', [
            'q' => $q,
            'typed' => $typed,
            'words' => $typed ? MinesSearch::words($q) : [],
            'groups' => $typed && $user ? $search->search($user, $q, 25) : [],
            'pages' => ! $typed && $user ? $search->pages($user) : [],
        ]);
    }
}
