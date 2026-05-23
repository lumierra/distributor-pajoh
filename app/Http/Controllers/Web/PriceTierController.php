<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PriceTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceTierController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', PriceTier::class);

        return Inertia::render('Products/PriceTiers', [
            'tiers' => PriceTier::query()
                ->withCount('prices')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PriceTier::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z_][A-Z0-9_]*$/', 'unique:price_tiers,code'],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $data['is_system'] = false;

        PriceTier::create($data);

        return back()->with('flash.success', 'Price tier dibuat.');
    }

    public function update(Request $request, PriceTier $priceTier): RedirectResponse
    {
        $this->authorize('update', $priceTier);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $priceTier->update($data);

        return back()->with('flash.success', 'Price tier diperbarui.');
    }

    public function destroy(PriceTier $priceTier): RedirectResponse
    {
        $this->authorize('delete', $priceTier);

        $priceTier->delete();

        return back()->with('flash.success', 'Price tier dihapus.');
    }
}
