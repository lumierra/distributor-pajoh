<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTypeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', CustomerType::class);

        return Inertia::render('Customers/Types', [
            'types' => CustomerType::query()
                ->withCount('customers')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerType::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z_][A-Z0-9_]*$/', 'unique:customer_types,code'],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        CustomerType::create($data);

        return back()->with('flash.success', 'Tipe customer dibuat.');
    }

    public function update(Request $request, CustomerType $customerType): RedirectResponse
    {
        $this->authorize('update', $customerType);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $customerType->update($data);

        return back()->with('flash.success', 'Tipe customer diperbarui.');
    }

    public function destroy(CustomerType $customerType): RedirectResponse
    {
        $this->authorize('delete', $customerType);

        $customerType->delete();

        return back()->with('flash.success', 'Tipe customer dihapus.');
    }
}
