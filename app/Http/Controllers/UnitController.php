<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $query = Unit::query();

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

        $units = $query->latest()->paginate(10);

        return view('units.index', compact(
            'units',
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

        return view('units.create', compact(
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

        Unit::create([
            'client_id' => $user->client_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit added successfully.');
    }

    public function edit(Unit $unit)
    {
        $user = Auth::user();

        if ($unit->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this unit.');
        }

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        return view('units.edit', compact(
            'unit',
            'user',
            'clientName',
            'branchName'
        ));
    }

    public function update(Request $request, Unit $unit)
    {
        $user = Auth::user();

        if ($unit->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this unit.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        $user = Auth::user();

        if ($unit->client_id != $user->client_id) {
            abort(403, 'Unauthorized access to this unit.');
        }

        $unit->delete();

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit deleted successfully.');
    }
}