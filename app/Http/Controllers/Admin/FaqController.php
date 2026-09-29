<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * FAQs shown on the Contact page and used by the chatbot (context + offline fallback).
 */
class FaqController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Faq::class);

        return view('admin.faqs.index', ['faqs' => Faq::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', Faq::class);

        return view('admin.faqs.form', ['faq' => new Faq(['is_active' => true, 'sort_order' => Faq::max('sort_order') + 1])]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Faq::create([...$request->validated(), 'sort_order' => $request->validated('sort_order') ?? 0]);

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ added.');
    }

    public function edit(Faq $faq): View
    {
        $this->authorize('view', $faq);

        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update([...$request->validated(), 'sort_order' => $request->validated('sort_order') ?? 0]);

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ saved.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorize('delete', $faq);

        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ deleted.');
    }
}
