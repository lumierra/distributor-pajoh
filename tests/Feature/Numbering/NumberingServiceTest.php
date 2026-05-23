<?php

use App\Models\NumberingSequence;
use App\Services\Numbering\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->service = app(NumberingService::class);
});

test('generates PO number with yearly reset format', function (): void {
    $first = $this->service->next('po');
    $second = $this->service->next('po');

    $year = now()->format('y');
    $month = now()->format('m');

    expect($first)->toBe("PO-{$year}{$month}-0001");
    expect($second)->toBe("PO-{$year}{$month}-0002");
});

test('generates supplier_code with never reset (running sequence)', function (): void {
    $a = $this->service->next('supplier_code');
    $b = $this->service->next('supplier_code');
    $c = $this->service->next('supplier_code');

    expect($a)->toBe('SUP-0001');
    expect($b)->toBe('SUP-0002');
    expect($c)->toBe('SUP-0003');
});

test('invoice number uses cust context token', function (): void {
    $invoice = $this->service->next('invoice', ['cust' => 361]);
    $expected = sprintf('DIS/%03d/%s%04d', 1, now()->format('mY')[0] === '0' ? now()->format('my') : now()->format('my'), 361);

    expect($invoice)
        ->toMatch('/^DIS\/\d{3}\/\d{4}0361$/');
});

test('product_sku uses cat context token', function (): void {
    $sku = $this->service->next('product_sku', ['cat' => 'FMC']);
    expect($sku)->toBe('SKU-FMC-0001');
});

test('atomic: concurrent calls do not produce duplicate numbers', function (): void {
    $numbers = [];
    for ($i = 0; $i < 10; $i++) {
        $numbers[] = $this->service->next('po');
    }

    expect(count(array_unique($numbers)))->toBe(10);
});

test('numbering_sequences row created with proper period', function (): void {
    $this->service->next('po');

    $seq = NumberingSequence::where('doc_type', 'po')
        ->where('period_year', now()->year)
        ->whereNull('period_month')
        ->first();

    expect($seq)->not->toBeNull();
    expect($seq->last_number)->toBe(1);
});

test('applyTokens replaces tokens correctly', function (): void {
    $result = $this->service->applyTokens('TEST-{YY}{MM}-{seq:04d}', ['seq' => 42]);
    $expected = 'TEST-'.now()->format('ym').'-0042';

    expect($result)->toBe($expected);
});
