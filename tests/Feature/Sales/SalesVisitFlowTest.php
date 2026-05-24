<?php

use App\Jobs\Sales\AutoCheckoutEndOfDayJob;
use App\Jobs\Sales\ExpireBypassRequestsJob;
use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesSchedule;
use App\Models\SalesVisit;
use App\Models\SalesVisitBypassRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDevicePendingRequest;
use App\Services\Sales\BypassService;
use App\Services\Sales\DeviceBindingGuard;
use App\Services\Sales\GeofenceValidator;
use App\Services\Sales\SalesScheduleResolver;
use App\Services\Sales\SalesVisitService;
use Database\Seeders\CustomerTypeSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PriceTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PriceTierSeeder::class);
    $this->seed(CustomerTypeSeeder::class);

    Storage::fake('public');
});

function svUser(string $code): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$code.'-'.$uniq,
        'username' => 'u_'.$code.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($code)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

function svCustomer(?float $lat = null, ?float $lng = null): Customer
{
    $tier = PriceTier::query()->where('code', 'ECERAN')->first();

    return Customer::create([
        'code' => 'CUST-SV-'.random_int(1000, 9999),
        'name' => 'Cust SV',
        'price_tier_id' => $tier->id,
        'credit_limit' => 1_000_000,
        'payment_term_days' => 7,
        'is_active' => true,
        'address' => 'Test',
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

// ── Schedule resolver ───────────────────────────────────────────────────

test('schedule recurring sesuai hari muncul di todayApplicable', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();
    $today = now();

    SalesSchedule::create([
        'sales_id' => $sales->id, 'customer_id' => $customer->id,
        'pattern' => 'recurring', 'day_of_week' => (int) $today->dayOfWeekIso,
        'is_active' => true,
    ]);

    $rows = app(SalesScheduleResolver::class)->todayFor($sales, $today);
    expect($rows->count())->toBe(1);
});

test('schedule recurring beda hari TIDAK muncul', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();
    $today = now();
    $otherDay = ((int) $today->dayOfWeekIso % 7) + 1;

    SalesSchedule::create([
        'sales_id' => $sales->id, 'customer_id' => $customer->id,
        'pattern' => 'recurring', 'day_of_week' => $otherDay,
        'is_active' => true,
    ]);

    $rows = app(SalesScheduleResolver::class)->todayFor($sales, $today);
    expect($rows->count())->toBe(0);
});

test('schedule one_time match tanggal exact', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();

    SalesSchedule::create([
        'sales_id' => $sales->id, 'customer_id' => $customer->id,
        'pattern' => 'one_time', 'visit_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $rows = app(SalesScheduleResolver::class)->todayFor($sales);
    expect($rows->count())->toBe(1);
});

test('schedule recurring + end_date < today tidak muncul', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();
    $today = now();

    SalesSchedule::create([
        'sales_id' => $sales->id, 'customer_id' => $customer->id,
        'pattern' => 'recurring', 'day_of_week' => (int) $today->dayOfWeekIso,
        'is_active' => true,
        'end_date' => $today->copy()->subDay()->toDateString(),
    ]);

    $rows = app(SalesScheduleResolver::class)->todayFor($sales);
    expect($rows->count())->toBe(0);
});

// ── Device binding ──────────────────────────────────────────────────────

test('first device auto-register kalau setting on (default)', function (): void {
    $sales = svUser(Role::CODE_SALES);

    $result = app(DeviceBindingGuard::class)->resolveOnLogin($sales, [
        'device_uuid' => 'UUID-FIRST',
        'device_name' => 'Galaxy A52',
        'ip' => '127.0.0.1',
    ]);

    expect($result['outcome'])->toBe(DeviceBindingGuard::OUTCOME_AUTO_REGISTER);
    expect($result['device'])->not->toBeNull();
    expect(UserDevice::where('user_id', $sales->id)->count())->toBe(1);
});

test('device baru saat sudah ada active → pending request', function (): void {
    $sales = svUser(Role::CODE_SALES);

    // Setup: ada device active sebelumnya
    UserDevice::create([
        'user_id' => $sales->id,
        'device_uuid' => 'UUID-A',
        'device_name' => 'Device A',
        'status' => UserDevice::STATUS_ACTIVE,
        'registered_at' => now(),
    ]);

    $result = app(DeviceBindingGuard::class)->resolveOnLogin($sales, [
        'device_uuid' => 'UUID-B',
        'device_name' => 'Device B',
        'ip' => '127.0.0.1',
    ]);

    expect($result['outcome'])->toBe(DeviceBindingGuard::OUTCOME_PENDING);
    expect(UserDevicePendingRequest::where('user_id', $sales->id)->count())->toBe(1);
});

test('login dari device active = allow + refresh last_login', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $device = UserDevice::create([
        'user_id' => $sales->id,
        'device_uuid' => 'UUID-X',
        'device_name' => 'X',
        'status' => UserDevice::STATUS_ACTIVE,
        'registered_at' => now()->subDays(5),
        'last_login_at' => now()->subHour(),
    ]);

    $result = app(DeviceBindingGuard::class)->resolveOnLogin($sales, [
        'device_uuid' => 'UUID-X',
        'device_name' => 'X',
        'ip' => '127.0.0.1',
    ]);

    expect($result['outcome'])->toBe(DeviceBindingGuard::OUTCOME_ALLOW);
    expect($result['device']->id)->toBe($device->id);
});

test('admin approve pending → revoke device lama (max_per_user=1)', function (): void {
    $admin = svUser(Role::CODE_SUPERADMIN);
    $sales = svUser(Role::CODE_SALES);

    $oldDevice = UserDevice::create([
        'user_id' => $sales->id, 'device_uuid' => 'OLD', 'device_name' => 'Old',
        'status' => UserDevice::STATUS_ACTIVE, 'registered_at' => now(),
    ]);

    $pending = UserDevicePendingRequest::create([
        'user_id' => $sales->id, 'device_uuid' => 'NEW', 'device_name' => 'New',
        'requested_at' => now(), 'status' => UserDevicePendingRequest::STATUS_PENDING,
    ]);

    $newDevice = app(DeviceBindingGuard::class)->approvePending($pending, $admin);

    expect($newDevice->status)->toBe(UserDevice::STATUS_ACTIVE);
    expect($oldDevice->fresh()->status)->toBe(UserDevice::STATUS_REVOKED);
    expect($pending->fresh()->status)->toBe(UserDevicePendingRequest::STATUS_APPROVED);
});

// ── Geofence ────────────────────────────────────────────────────────────

test('haversine distance accurate', function (): void {
    // Langsa city → Banda Aceh ~330km. Use approximate coordinates.
    $d = app(GeofenceValidator::class)->distanceMeter(4.4684, 97.9610, 5.5483, 95.3238);
    expect($d)->toBeGreaterThan(280_000); // sekitar 280-330 km
    expect($d)->toBeLessThan(340_000);
});

test('check-in dalam radius sukses', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684,
        'longitude' => 97.9610,
        'accuracy_meter' => 5,
        'is_mock_location' => false,
    ], null, null);

    expect($visit->status)->toBe(SalesVisit::STATUS_ACTIVE);
});

test('check-in luar radius → ValidationException', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    expect(fn () => app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 5.5483, // ~330 km jauh
        'longitude' => 95.3238,
        'is_mock_location' => false,
    ], null, null))->toThrow(ValidationException::class);
});

test('check-in customer tanpa koordinat → allowed (geofence skipped)', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(); // no lat/lng

    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.5, 'longitude' => 97.5,
        'is_mock_location' => false,
    ], null, null);

    expect($visit->status)->toBe(SalesVisit::STATUS_ACTIVE);
});

test('check-in dengan is_mock_location=true → block', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    expect(fn () => app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => true,
    ], null, null))->toThrow(ValidationException::class);
});

test('check-in saat ada active visit lain → auto-checkout sebelumnya', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $cust1 = svCustomer(lat: 4.4684, lng: 97.9610);
    $cust2 = svCustomer(lat: 4.4684, lng: 97.9610);

    $first = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $cust1->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    $second = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $cust2->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    expect($first->fresh()->status)->toBe(SalesVisit::STATUS_COMPLETED);
    expect($first->fresh()->auto_checkout_reason)->toBe(SalesVisit::AUTO_REASON_NEXT_VISIT);
    expect($second->status)->toBe(SalesVisit::STATUS_ACTIVE);
});

// ── Check-out + cancel ──────────────────────────────────────────────────

test('check-out manual computes duration', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    Carbon::setTestNow($visit->checked_in_at->addMinutes(45));

    $visit = app(SalesVisitService::class)->checkout($visit, $sales, []);

    expect($visit->status)->toBe(SalesVisit::STATUS_COMPLETED);
    expect((int) $visit->duration_minutes)->toBe(45);

    Carbon::setTestNow(null);
});

test('cancel visit yang sudah ada SO → ValidationException', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    // Simulate SO linked
    SalesOrder::query()->create([
        'so_number' => 'SO-TEST-'.random_int(1000, 9999),
        'customer_id' => $customer->id,
        'sales_id' => $sales->id,
        'so_date' => now()->toDateString(),
        'payment_term_days' => 7,
        'status' => 'draft',
        'fiscal_year' => (int) now()->format('Y'),
        'visit_id' => $visit->id,
        'subtotal' => 0, 'total' => 0,
    ]);

    expect(fn () => app(SalesVisitService::class)->cancel($visit->refresh(), $sales, 'Salah klik'))
        ->toThrow(ValidationException::class);
});

// ── Bypass ──────────────────────────────────────────────────────────────

test('bypass approved valid 30 menit → check-in sukses meski luar radius', function (): void {
    $admin = svUser(Role::CODE_SUPERADMIN);
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    $req = app(BypassService::class)->requestBypass($sales, $customer, [
        'reason' => 'Outlet pindah lokasi sementara',
    ]);
    app(BypassService::class)->approve($req, $admin);

    // Check-in dari luar radius — biasanya gagal, tapi bypass aktif
    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 5.5, // jauh
        'longitude' => 95.3,
        'is_mock_location' => false,
    ], null, null);

    expect($visit->status)->toBe(SalesVisit::STATUS_ACTIVE);
    expect((bool) $visit->bypass_geofence)->toBeTrue();
});

test('expire stale bypass requests → status expired', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();

    SalesVisitBypassRequest::create([
        'sales_id' => $sales->id,
        'customer_id' => $customer->id,
        'reason' => 'lama',
        'requested_at' => now()->subHour(), // 60 menit lalu
        'status' => SalesVisitBypassRequest::STATUS_PENDING,
    ]);

    $count = (new ExpireBypassRequestsJob)->handle(app(BypassService::class));
    expect($count)->toBeGreaterThan(0);
});

// ── Auto-checkout job ───────────────────────────────────────────────────

test('AutoCheckoutEndOfDayJob — close all active visits', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    expect(SalesVisit::where('status', SalesVisit::STATUS_ACTIVE)->count())->toBe(1);

    $count = (new AutoCheckoutEndOfDayJob)->handle();

    expect($count)->toBe(1);
    expect(SalesVisit::where('status', SalesVisit::STATUS_ACTIVE)->count())->toBe(0);

    $visit = SalesVisit::query()->first();
    expect($visit->auto_checkout_reason)->toBe(SalesVisit::AUTO_REASON_END_OF_DAY);
});

// ── LinksToActiveVisit trait ────────────────────────────────────────────

test('SO created saat ada active visit → visit_id auto-set', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer(lat: 4.4684, lng: 97.9610);

    $visit = app(SalesVisitService::class)->checkin($sales, [
        'customer_id' => $customer->id,
        'latitude' => 4.4684, 'longitude' => 97.9610,
        'is_mock_location' => false,
    ], null, null);

    $this->actingAs($sales);

    $so = SalesOrder::create([
        'so_number' => 'SO-LINK-'.random_int(1000, 9999),
        'customer_id' => $customer->id,
        'sales_id' => $sales->id,
        'so_date' => now()->toDateString(),
        'payment_term_days' => 7,
        'status' => 'draft',
        'fiscal_year' => (int) now()->format('Y'),
        'subtotal' => 0, 'total' => 0,
    ]);

    expect($so->visit_id)->toBe($visit->id);
});

// ── Controller permission ───────────────────────────────────────────────

test('mobile API: today schedule endpoint accessible', function (): void {
    $sales = svUser(Role::CODE_SALES);
    $customer = svCustomer();
    $today = now();

    SalesSchedule::create([
        'sales_id' => $sales->id, 'customer_id' => $customer->id,
        'pattern' => 'recurring', 'day_of_week' => (int) $today->dayOfWeekIso,
        'is_active' => true,
    ]);

    $this->actingAs($sales, 'sanctum')
        ->getJson(route('api.sales.schedules.today'))
        ->assertOk()
        ->assertJsonStructure(['date', 'count', 'schedules']);
});

test('web: sales hanya lihat schedule sendiri', function (): void {
    $admin = svUser(Role::CODE_SUPERADMIN);
    $salesA = svUser(Role::CODE_SALES);
    $salesB = svUser(Role::CODE_SALES);
    $customer = svCustomer();

    $scheduleA = SalesSchedule::create([
        'sales_id' => $salesA->id, 'customer_id' => $customer->id,
        'pattern' => 'one_time', 'visit_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    expect($salesA->can('view', $scheduleA))->toBeTrue();
    expect($salesB->can('view', $scheduleA))->toBeFalse();
    expect($admin->can('view', $scheduleA))->toBeTrue();
});
