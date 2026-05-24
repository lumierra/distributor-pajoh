<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\YearEndClosing;
use App\Services\Closing\YearEndClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class YearEndClosingController extends Controller
{
    public function __construct(private readonly YearEndClosingService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->ensureSuperadmin($request);

        $closings = YearEndClosing::query()
            ->with('closer:id,name')
            ->orderByDesc('fiscal_year')
            ->get();

        $currentYear = (int) now()->format('Y');
        $previousYear = $currentYear - 1;
        $preCheck = $this->service->preCheck($previousYear);

        return Inertia::render('YearEndClosings/Index', [
            'closings' => $closings,
            'previousYear' => $previousYear,
            'preCheck' => $preCheck,
        ]);
    }

    public function show(YearEndClosing $yearEndClosing, Request $request): InertiaResponse
    {
        $this->ensureSuperadmin($request);

        $yearEndClosing->load('closer:id,name');

        return Inertia::render('YearEndClosings/Show', [
            'closing' => $yearEndClosing,
        ]);
    }

    public function preCheck(int $fiscalYear, Request $request)
    {
        $this->ensureSuperadmin($request);

        return response()->json($this->service->preCheck($fiscalYear));
    }

    public function execute(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:2020|max:2100',
            'confirm' => 'required|accepted',
        ]);

        $closing = $this->service->execute((int) $data['fiscal_year'], $request->user());

        return redirect()
            ->route('year-end-closings.show', $closing)
            ->with('flash.success', "Year-end closing FY{$closing->fiscal_year} completed.");
    }

    private function ensureSuperadmin(Request $request): void
    {
        $user = $request->user();
        if ($user === null || ! $user->isSuperadmin()) {
            abort(403);
        }
    }
}
