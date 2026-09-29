<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Image credits page: every published photo with its author, licence and source (CLAUDE.md
 * data rules; CC BY and CC BY-SA require attribution).
 */
class CreditsController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('legal.credits', [
            'photos' => Media::published()
                ->with('mediable')
                ->where(fn ($q) => $q->whereNotNull('author')->orWhereNotNull('license'))
                ->orderBy('mediable_type')->orderBy('mediable_id')->orderBy('sort_order')
                ->paginate(60),
        ]);
    }
}
