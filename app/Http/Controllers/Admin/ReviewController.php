<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Traveller reviews: shown on the Home page as testimonials only after approval (FR-30).
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Review::class);

        $reviews = Review::with('user')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('author_name', 'like', "%{$s}%")->orWhere('comment', 'like', "%{$s}%")))
            ->when($request->query('status') === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($request->query('status') === 'approved', fn ($q) => $q->where('is_approved', true))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews]);
    }

    public function create(): View
    {
        $this->authorize('create', Review::class);

        return view('admin.reviews.form', ['review' => new Review(['rating' => 5])]);
    }

    public function store(ReviewRequest $request): RedirectResponse
    {
        $review = new Review;
        $review->forceFill($request->validated())->save();

        return redirect()->route('admin.reviews.index')->with('status', 'Review added.');
    }

    public function edit(Review $review): View
    {
        $this->authorize('view', $review);

        return view('admin.reviews.form', ['review' => $review]);
    }

    public function update(ReviewRequest $request, Review $review): RedirectResponse
    {
        $review->forceFill($request->validated())->save();

        return redirect()->route('admin.reviews.index')->with('status', 'Review saved.');
    }

    public function approve(Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->forceFill(['is_approved' => ! $review->is_approved])->save();

        return back()->with('status', $review->is_approved ? 'Review approved; it can now appear on the Home page.' : 'Review hidden from the site.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('admin.reviews.index')->with('status', 'Review deleted.');
    }
}
