<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Models\District;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::withCount(['places', 'districts'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.form', ['category' => new Category, 'districts' => District::orderBy('name')->get()]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->safe()->except('districts'));
        $category->districts()->sync($request->validated('districts', []));

        return redirect()->route('admin.categories.index')->with('status', "Category \"{$category->name}\" created.");
    }

    public function edit(Category $category): View
    {
        $this->authorize('view', $category);

        return view('admin.categories.form', ['category' => $category->load('districts'), 'districts' => District::orderBy('name')->get()]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->safe()->except('districts'));
        $category->districts()->sync($request->validated('districts', []));

        return redirect()->route('admin.categories.index')->with('status', 'Category saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->places()->exists()) {
            return back()->with('error', "\"{$category->name}\" is used by places. Move them to another category first.");
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', "Category \"{$category->name}\" deleted.");
    }
}
