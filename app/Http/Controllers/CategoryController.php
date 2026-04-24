<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $query = Category::query();

        if (!empty($user->client_id)) {
            $query->where('client_id', $user->client_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $categories = $query->latest()->paginate(10);

        return view('categories.index', compact(
            'categories',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function create()
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('categories.create', compact(
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Category::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category added successfully.');
    }

    public function edit(Category $category)
    {
        $user = Auth::user();

        if ($category->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this category.');
        }

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('categories.edit', compact(
            'category',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function update(Request $request, Category $category)
    {
        $user = Auth::user();

        if ($category->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this category.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $user = Auth::user();

        if ($category->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this category.');
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}