<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opname\CancelOpnameRequest;
use App\Http\Requests\Opname\StoreOpnameRequest;
use App\Http\Requests\Opname\UpdateOpnameRequest;
use App\Models\StockOpname;
use App\Services\Inventory\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OpnameController extends Controller
{
    public function __construct(private readonly StockOpnameService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', StockOpname::class);

        $query = StockOpname::query()
            ->withCount('items')
            ->with('creator:id,name')
            ->orderByDesc('opname_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where('opname_number', 'like', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $totals = StockOpname::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [StockOpname::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [StockOpname::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [StockOpname::STATUS_CANCELLED])
            ->first();

        return Inertia::render('Inventory/Opnames/Index', [
            'opnames' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'posted' => (int) ($totals->posted ?? 0),
                'cancelled' => (int) ($totals->cancelled ?? 0),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', StockOpname::class);

        return Inertia::render('Inventory/Opnames/Create');
    }

    public function store(StoreOpnameRequest $request): RedirectResponse
    {
        $opname = $this->service->createDraft($request->validated(), $request->user());

        if ($opname->items()->count() === 0) {
            // Tidak ada batch berstok → hapus sesi kosong, beri tahu user.
            $opname->forceDelete();

            return redirect()
                ->route('opnames.index')
                ->with('flash.error', 'Tidak ada stok untuk di-opname (semua batch kosong).');
        }

        return redirect()
            ->route('opnames.edit', $opname)
            ->with('flash.success', "Opname {$opname->opname_number} dibuat. Isi hasil hitung fisik.");
    }

    public function show(StockOpname $opname): InertiaResponse
    {
        $this->authorize('view', $opname);

        $opname->load(['items', 'creator:id,name', 'poster:id,name', 'canceller:id,name']);

        return Inertia::render('Inventory/Opnames/Show', [
            'opname' => $opname,
            'canEdit' => $opname->canBeEdited() && (request()->user()?->can('update', $opname) ?? false),
            'canPost' => $opname->canBePosted() && (request()->user()?->can('post', $opname) ?? false),
            'canCancel' => $opname->canBeCancelled() && (request()->user()?->can('cancel', $opname) ?? false),
        ]);
    }

    public function edit(StockOpname $opname): InertiaResponse
    {
        $this->authorize('update', $opname);
        abort_unless($opname->canBeEdited(), 422, 'Opname tidak bisa diedit.');

        $opname->load('items');

        return Inertia::render('Inventory/Opnames/Edit', [
            'opname' => $opname,
        ]);
    }

    public function update(UpdateOpnameRequest $request, StockOpname $opname): RedirectResponse
    {
        $data = $request->validated();

        $counts = [];
        foreach ($data['items'] ?? [] as $row) {
            $counts[(int) $row['id']] = $row['counted_qty'] ?? null;
        }
        unset($data['items']);

        $this->service->update($opname, $data, $counts, $request->user());

        return redirect()
            ->route('opnames.show', $opname)
            ->with('flash.success', 'Hasil hitung opname disimpan.');
    }

    public function post(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->authorize('post', $opname);

        $this->service->post($opname, $request->user());

        return back()->with('flash.success', "Opname {$opname->opname_number} di-posting. Stok disesuaikan ke hasil hitung.");
    }

    public function cancel(CancelOpnameRequest $request, StockOpname $opname): RedirectResponse
    {
        $this->service->cancel($opname, $request->validated('cancel_reason'), $request->user());

        return back()->with('flash.success', "Opname {$opname->opname_number} dibatalkan.");
    }
}
