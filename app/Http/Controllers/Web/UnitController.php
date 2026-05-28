<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master satuan global (PCS, KARDUS, LUSIN, dll). Dipakai sebagai sumber
 * kebenaran untuk dropdown saat input ProductUnit per produk.
 */
class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $query = Unit::query()->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return Inertia::render('Units/Index', [
            'units' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'active' => $request->input('active'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Unit::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', Rule::unique('units', 'name')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Unit::create($data);

        return back()->with('flash.success', 'Satuan dibuat.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', Rule::unique('units', 'name')->ignore($unit->id)->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $unit->update($data);

        return back()->with('flash.success', 'Satuan diperbarui.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return back()->with('flash.success', 'Satuan dihapus.');
    }
}
