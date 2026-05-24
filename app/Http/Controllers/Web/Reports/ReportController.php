<?php

namespace App\Http\Controllers\Web\Reports;

use App\Exports\Reports\ArAgingExport;
use App\Exports\Reports\MarginExport;
use App\Exports\Reports\SalesActivityExport;
use App\Exports\Reports\SalesSummaryExport;
use App\Exports\Reports\StockPositionExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Services\Reports\ArAgingReportService;
use App\Services\Reports\MarginReportService;
use App\Services\Reports\ReportService;
use App\Services\Reports\SalesActivityReportService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StockReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $sales,
        private readonly StockReportService $stock,
        private readonly ArAgingReportService $arAging,
        private readonly MarginReportService $margin,
        private readonly SalesActivityReportService $salesActivity,
        private readonly ReportService $orchestrator,
    ) {}

    public function salesIndex(Request $request): InertiaResponse
    {
        $this->authorizeReport($request, 'viewSales');

        $filters = $this->parseFilters($request, scopeSales: true);

        return Inertia::render('Reports/Sales/Index', [
            'kpi' => $this->sales->getSummary($filters),
            'rows' => $this->sales->getTable($filters),
            'filters' => $filters,
            'salesUsers' => $this->salesUsersForFilter($request),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'sku', 'name']),
        ]);
    }

    public function stockIndex(Request $request): InertiaResponse
    {
        $this->authorizeReport($request, 'viewStock');

        $filters = ['date' => $request->input('date', now()->toDateString())];
        if ($pid = $request->input('product_id')) {
            $filters['product_id'] = (int) $pid;
        }

        return Inertia::render('Reports/Stock/Index', [
            'kpi' => $this->stock->getSummary($filters),
            'rows' => $this->stock->getTable($filters),
            'filters' => $filters,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'sku', 'name']),
        ]);
    }

    public function arAgingIndex(Request $request): InertiaResponse
    {
        $this->authorizeReport($request, 'viewArAging');

        $filters = ['date' => $request->input('date', now()->toDateString())];
        if ($cid = $request->input('customer_id')) {
            $filters['customer_id'] = (int) $cid;
        }

        return Inertia::render('Reports/ArAging/Index', [
            'kpi' => $this->arAging->getSummary($filters),
            'rows' => $this->arAging->getTable($filters),
            'filters' => $filters,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
        ]);
    }

    public function marginIndex(Request $request): InertiaResponse
    {
        $this->authorizeReport($request, 'viewMargin');

        $filters = $this->parseFilters($request, scopeSales: false);

        return Inertia::render('Reports/Margin/Index', [
            'kpi' => $this->margin->getSummary($filters),
            'byProduct' => $this->margin->getByProduct($filters),
            'byCustomer' => $this->margin->getByCustomer($filters),
            'filters' => $filters,
        ]);
    }

    public function salesActivityIndex(Request $request): InertiaResponse
    {
        $this->authorizeReport($request, 'viewSalesActivity');

        $filters = $this->parseFilters($request, scopeSales: true);

        return Inertia::render('Reports/SalesActivity/Index', [
            'kpi' => $this->salesActivity->getSummary($filters),
            'bySales' => $this->salesActivity->getBySales($filters),
            'filters' => $filters,
            'salesUsers' => $this->salesUsersForFilter($request),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->authorizeReport($request, 'regenerate');

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now()->subDay();
        $counts = $this->orchestrator->regenerateForDate($date);

        $summary = collect($counts)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

        return back()->with('flash.success', "Snapshot di-regenerate untuk {$date->toDateString()} — {$summary}.");
    }

    public function export(Request $request, string $reportType): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeReport($request, 'export', $reportType);

        $filters = $request->all();
        $year = now()->format('Y');
        $filename = sprintf('%s_%s.xlsx', $reportType, Str::uuid()->toString());
        $path = "report_exports/{$year}/{$request->user()->id}/{$filename}";

        $exporter = match ($reportType) {
            'sales_summary' => new SalesSummaryExport($this->sales, $filters),
            'stock_position' => new StockPositionExport($this->stock, $filters),
            'ar_aging' => new ArAgingExport($this->arAging, $filters),
            'margin' => new MarginExport($this->margin, $filters),
            'sales_activity' => new SalesActivityExport($this->salesActivity, $filters),
            default => null,
        };

        if ($exporter === null) {
            return back()->with('flash.error', "Unknown report type: {$reportType}");
        }

        Excel::store($exporter, $path, 'local');

        $absolute = Storage::disk('local')->path($path);
        $size = file_exists($absolute) ? filesize($absolute) : null;

        ReportExport::create([
            'user_id' => $request->user()->id,
            'report_type' => $reportType,
            'format' => 'xlsx',
            'filters' => $filters,
            'rows_count' => $exporter->collection()->count(),
            'file_size_bytes' => $size,
            'file_path' => $path,
            'exported_at' => now(),
        ]);

        return response()->download($absolute, $filename)->deleteFileAfterSend(false);
    }

    private function authorizeReport(Request $request, string $method, ?string $arg = null): void
    {
        $policy = app(ReportPolicy::class);
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // Honor before() (superadmin bypass)
        $before = $policy->before($user, $method);
        if ($before === true) {
            return;
        }

        $allowed = $arg !== null ? $policy->{$method}($user, $arg) : $policy->{$method}($user);
        if (! $allowed) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilters(Request $request, bool $scopeSales): array
    {
        $filters = [
            'from' => $request->input('from', now()->subDays(30)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
        ];
        if ($sid = $request->input('sales_id')) {
            $filters['sales_id'] = (int) $sid;
        }
        if ($cid = $request->input('customer_id')) {
            $filters['customer_id'] = (int) $cid;
        }
        if ($pid = $request->input('product_id')) {
            $filters['product_id'] = (int) $pid;
        }

        // Sales scope — sales hanya lihat data sendiri
        $user = $request->user();
        if ($scopeSales && $user && ! $user->isSuperadmin() && $user->hasRole(Role::CODE_SALES)) {
            $filters['sales_id'] = $user->id;
        }

        return $filters;
    }

    /**
     * @return Collection<int, User>
     */
    private function salesUsersForFilter(Request $request): Collection
    {
        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole(Role::CODE_SALES)) {
            return User::query()->where('id', $user->id)->get(['id', 'name']);
        }

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
