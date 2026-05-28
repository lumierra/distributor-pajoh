<?php

namespace App\Imports\Customer;

use App\Models\CustomerType;
use App\Models\Role;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

/**
 * Bulk-import customer dari Excel.
 *
 * Strategi: skip & report (B). Tiap row di-validate independen; row valid masuk
 * langsung lewat CustomerService.create, row error dikumpulkan ke $errors[]
 * untuk dilaporkan ke admin (download error.xlsx).
 *
 * Hard limit MAX_ROWS untuk safety. Lookup kode → ID via map yg di-cache sekali.
 */
class CustomerImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    public const MAX_ROWS = 1000;

    /** @var array<int, array{row: int, data: array<string, mixed>, errors: array<string>}> */
    public array $errors = [];

    public int $created = 0;

    public int $skipped = 0;

    private array $customerTypeMap;

    private array $salesUserMap;

    public function __construct(
        private readonly CustomerService $service,
    ) {
        // Force snake_case heading agar konsisten (Maatwebsite default lowercase + snake)
        HeadingRowFormatter::default('slug');

        $this->customerTypeMap = CustomerType::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->toArray();

        $this->salesUserMap = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
            ->where('is_active', true)
            ->pluck('id', 'username')
            ->toArray();
    }

    public function startRow(): int
    {
        return 1;
    }

    public function collection($rows): void
    {
        if ($rows->count() > self::MAX_ROWS) {
            $this->errors[] = [
                'row' => 0,
                'data' => [],
                'errors' => [sprintf('File berisi %d baris, maksimum %d per upload.', $rows->count(), self::MAX_ROWS)],
            ];

            return;
        }

        foreach ($rows as $idx => $row) {
            $rowNumber = $idx + 2; // header di row 1, data mulai row 2
            $raw = is_array($row) ? $row : $row->toArray();

            // Skip baris kosong total
            if ($this->isEmptyRow($raw)) {
                continue;
            }

            [$resolved, $codeErrors] = $this->resolveCodes($raw);

            // Validasi pakai rules subset (yang relevan dgn template simplified)
            $validator = Validator::make($resolved, [
                'name' => ['required', 'string', 'max:128'],
                'owner_name' => ['nullable', 'string', 'max:128'],
                'customer_type_id' => ['nullable', 'integer', 'exists:customer_types,id'],
                'whatsapp' => ['nullable', 'string', 'max:32'],
                'area' => ['nullable', 'string', 'max:128'],
                'assigned_sales_id' => ['nullable', 'integer', 'exists:users,id'],
                'credit_limit' => ['nullable', 'numeric', 'min:0'],
                'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'is_active' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string'],
            ]);

            if (! empty($codeErrors) || $validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'data' => $raw,
                    'errors' => array_merge($codeErrors, $validator->errors()->all()),
                ];
                $this->skipped++;

                continue;
            }

            try {
                $this->service->create($validator->validated());
                $this->created++;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'data' => $raw,
                    'errors' => [$e->getMessage()],
                ];
                $this->skipped++;
            }
        }
    }

    /**
     * Konversi kolom kode jadi *_id. Return [resolved, codeErrors] — codeErrors
     * berisi pesan untuk code yang diisi tapi tidak ditemukan di master.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function resolveCodes(array $row): array
    {
        $resolved = $row;
        $errors = [];

        $rawTypeCode = $row['customer_type_code'] ?? null;
        if ($rawTypeCode !== null && $rawTypeCode !== '') {
            $id = $this->lookupCode($rawTypeCode, $this->customerTypeMap);
            if ($id === null) {
                $errors[] = "customer_type_code '{$rawTypeCode}' tidak ditemukan di master.";
            }
            $resolved['customer_type_id'] = $id;
        } else {
            $resolved['customer_type_id'] = null;
        }
        unset($resolved['customer_type_code']);

        $rawSalesUsername = $row['assigned_sales_username'] ?? null;
        if ($rawSalesUsername !== null && $rawSalesUsername !== '') {
            $id = $this->lookupCode($rawSalesUsername, $this->salesUserMap);
            if ($id === null) {
                $errors[] = "assigned_sales_username '{$rawSalesUsername}' bukan sales aktif.";
            }
            $resolved['assigned_sales_id'] = $id;
        } else {
            $resolved['assigned_sales_id'] = null;
        }
        unset($resolved['assigned_sales_username']);

        if (array_key_exists('is_active', $resolved)) {
            $val = $resolved['is_active'];
            if ($val === '' || $val === null) {
                $resolved['is_active'] = true;
            } else {
                $resolved['is_active'] = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return [$resolved, $errors];
    }

    private function lookupCode(mixed $code, array $map): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $key = trim((string) $code);

        return $map[$key] ?? null;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '' && $value !== false) {
                return false;
            }
        }

        return true;
    }
}
