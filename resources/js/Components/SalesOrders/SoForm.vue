<script setup>
import { AlertTriangle, Loader2, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SearchableSelect from '@/Components/Shared/SearchableSelect.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    form: { type: Object, required: true },
    customers: { type: Array, required: true },
    salesUsers: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Simpan' },
});

const emit = defineEmits(['submit']);

const selectedCustomer = ref(null);
// productCatalog: list dari endpoint product-catalog. Tiap product punya
// `options[]` = kombinasi (supplier × satuan) dgn cost & sell price.
const productCatalog = ref([]);
const catalogLoaded = ref(false);

const customerTags = computed(() => selectedCustomer.value?.tags ?? []);
const hasProblemOutlet = computed(() => customerTags.value.includes('problem_outlet'));
const hasSlowPayer = computed(() => customerTags.value.includes('slow_payer'));

// Ambil katalog harga. Harga jual sudah customer-aware di backend (paket
// per-customer → sales-group → paket pertama), jadi kirim customer_id & sales_id.
// `force` dipakai saat customer berganti agar reload walau sudah pernah load.
async function loadCatalog(force = false) {
    if (catalogLoaded.value && !force) return;
    const params = new URLSearchParams();
    if (props.form.customer_id) params.set('customer_id', props.form.customer_id);
    if (props.form.sales_id) params.set('sales_id', props.form.sales_id);
    const qs = params.toString();
    try {
        const res = await fetch(route('sales-orders.product-catalog') + (qs ? `?${qs}` : ''), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        productCatalog.value = data.products ?? [];
    } catch {
        productCatalog.value = [];
    } finally {
        catalogLoaded.value = true;
    }
}

// Setelah katalog berubah (mis. ganti customer), samakan _unit_price tiap baris
// item yang sudah dipilih dengan harga terbaru. Item bonus tetap 0.
function repriceItems() {
    props.form.items.forEach((row) => {
        if (row.is_bonus || !row.product_id || !row.product_unit_id) return;
        const prod = productCatalog.value.find((p) => p.product_id === row.product_id);
        const opt = prod?.options.find(
            (o) => o.product_unit_id === row.product_unit_id
                && (row.supplier_id == null || o.supplier_id === row.supplier_id),
        );
        if (opt) row._unit_price = opt.sell_price;
    });
}

// Payment term default kalau customer tak punya term sendiri.
const DEFAULT_PAYMENT_TERM = 7;

function onCustomerChange(v) {
    props.form.customer_id = v ? Number(v) : null;
    const cust = props.customers.find((c) => c.id === props.form.customer_id);
    selectedCustomer.value = cust ?? null;
    // Ganti customer → ikuti payment term-nya; fallback ke default 7.
    if (cust) {
        props.form.payment_term_days = cust.payment_term_days ?? DEFAULT_PAYMENT_TERM;
    }
    // Harga jual bergantung customer (paket harga per-customer) → reload katalog.
    loadCatalog(true).then(repriceItems);
}

function onSalesChange(v) {
    props.form.sales_id = v ? Number(v) : null;
    // Sales berpengaruh ke paket sales-group → reload katalog & samakan harga.
    loadCatalog(true).then(repriceItems);
}

function addItem() {
    props.form.items.push({
        product_id: null,
        product_unit_id: null,
        supplier_id: null,
        qty: 1,
        discount_type: null, // null | 'percent' | 'rp'
        discount_value: 0,
        is_bonus: false,
        notes: '',
        // helper UI state
        _option_key: '', // "supplier_id|product_unit_id"
        _unit_price: 0,
        _product_name: null,
    });
}

function removeItem(idx) {
    props.form.items.splice(idx, 1);
}

function onProductChange(idx, productId) {
    const row = props.form.items[idx];
    const id = productId ? Number(productId) : null;
    row.product_id = id;
    row.product_unit_id = null;
    row.supplier_id = null;
    row._option_key = '';
    row._unit_price = 0;
    row._product_name = null;
    if (!id) return;
    const prod = productCatalog.value.find((p) => p.product_id === id);
    if (prod) {
        row._product_name = prod.name;
        // auto-pick option pertama (kalau cuma 1, sales gak perlu pilih lagi)
        if (prod.options.length > 0) {
            const opt = prod.options[0];
            row.product_unit_id = opt.product_unit_id;
            row.supplier_id = opt.supplier_id;
            row._option_key = `${opt.supplier_id}|${opt.product_unit_id}`;
            row._unit_price = opt.sell_price;
        }
    }
}

function onOptionChange(idx, key) {
    const row = props.form.items[idx];
    row._option_key = key;
    if (!row.product_id || !key) {
        row.product_unit_id = null;
        row.supplier_id = null;
        row._unit_price = 0;
        return;
    }
    const prod = productCatalog.value.find((p) => p.product_id === row.product_id);
    const opt = prod?.options.find((o) => `${o.supplier_id}|${o.product_unit_id}` === key);
    if (opt) {
        row.product_unit_id = opt.product_unit_id;
        row.supplier_id = opt.supplier_id;
        row._unit_price = opt.sell_price;
    }
}

function optionsForRow(row) {
    if (!row.product_id) return [];
    const prod = productCatalog.value.find((p) => p.product_id === row.product_id);
    return prod?.options ?? [];
}

// Harga net per unit setelah diskon per-item (% atau Rp). Cerminan backend.
function lineNet(row) {
    if (row.is_bonus) return 0;
    const price = Number(row._unit_price) || 0;
    const val = Number(row.discount_value) || 0;
    let net = price;
    if (row.discount_type === 'percent') net = price * (1 - Math.max(0, Math.min(100, val)) / 100);
    else if (row.discount_type === 'rp') net = price - val;
    return Math.round(Math.max(0, net) * 100) / 100;
}

function lineSubtotal(row) {
    return Math.round(lineNet(row) * (Number(row.qty) || 0) * 100) / 100;
}

// Potongan diskon per baris (harga penuh − net) × qty, untuk keterangan totals.
function lineDiscountAmount(row) {
    if (row.is_bonus) return 0;
    const full = (Number(row._unit_price) || 0) * (Number(row.qty) || 0);
    return Math.max(0, Math.round((full - lineSubtotal(row)) * 100) / 100);
}

const subtotal = computed(() => props.form.items.reduce((s, r) => s + lineSubtotal(r), 0));
const itemDiscountTotal = computed(() => props.form.items.reduce((s, r) => s + lineDiscountAmount(r), 0));
const headerDiscAmount = computed(() => {
    const type = props.form.header_discount_type;
    const val = Number(props.form.header_discount_value) || 0;
    if (type === 'rp') return Math.min(subtotal.value, val);
    if (type === 'percent') return Math.round(subtotal.value * val) / 100;
    return 0;
});
const cashbackAmount = computed(() =>
    Math.min(Math.max(0, Number(props.form.cashback) || 0), Math.max(0, subtotal.value - headerDiscAmount.value)),
);
const total = computed(() => Math.max(0, subtotal.value - headerDiscAmount.value - cashbackAmount.value));

// Set tipe diskon item; reset value kalau dikosongkan.
function setDiscountType(row, type) {
    row.discount_type = type || null;
    if (!row.discount_type) row.discount_value = 0;
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v) || 0);
}

// Load catalog sekali saat form mount, lalu hydrate existing items kalau Edit mode.
loadCatalog().then(() => {
    if (props.form.customer_id) {
        const cust = props.customers.find((c) => c.id === Number(props.form.customer_id));
        selectedCustomer.value = cust ?? null;
    }
    props.form.items.forEach((row) => {
        if (row.product_id && row.product_unit_id && row.supplier_id) {
            const prod = productCatalog.value.find((p) => p.product_id === row.product_id);
            if (prod) row._product_name = prod.name;
            const opt = prod?.options.find(
                (o) => o.product_unit_id === row.product_unit_id && o.supplier_id === row.supplier_id,
            );
            if (opt) {
                row._option_key = `${opt.supplier_id}|${opt.product_unit_id}`;
                row._unit_price = opt.sell_price;
            }
        }
    });
});

watch(
    () => props.form.customer_id,
    (val) => {
        if (val) {
            const cust = props.customers.find((c) => c.id === Number(val));
            selectedCustomer.value = cust ?? null;
        } else {
            selectedCustomer.value = null;
        }
    },
);
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Header -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3.5">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-3.5">
                <!-- Baris 1: Customer (2 kolom) + Sales -->
                <div class="space-y-1.5 sm:col-span-2">
                    <Label class="text-xs font-medium">Customer *</Label>
                    <Select
                        :model-value="form.customer_id ? String(form.customer_id) : ''"
                        @update:model-value="onCustomerChange"
                    >
                        <SelectTrigger class="h-10 w-full rounded-xl">
                            <SelectValue placeholder="Pilih customer" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                {{ c.name }} <span class="text-xs text-muted-foreground">({{ c.code }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.customer_id" class="text-xs text-destructive">{{ form.errors.customer_id }}</p>
                </div>
                <div v-if="salesUsers.length > 0" class="space-y-1.5">
                    <Label class="text-xs font-medium">Sales (atas nama)</Label>
                    <Select
                        :model-value="form.sales_id ? String(form.sales_id) : ''"
                        @update:model-value="onSalesChange"
                    >
                        <SelectTrigger class="h-10 w-full rounded-xl">
                            <SelectValue placeholder="(diri sendiri)" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="u in salesUsers" :key="u.id" :value="String(u.id)">
                                {{ u.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Baris 2: Tanggal SO, ETA, Payment Term -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-medium">Tanggal SO *</Label>
                    <Input v-model="form.so_date" type="date" required class="h-10 w-full rounded-xl" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs font-medium">ETA Pengiriman</Label>
                    <Input v-model="form.eta_date" type="date" class="h-10 w-full rounded-xl" />
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs font-medium">Payment Term (hari)</Label>
                    <Input v-model="form.payment_term_days" type="number" min="0" max="365" class="h-10 w-full rounded-xl font-mono" />
                </div>

                <!-- Baris 3: Header Diskon -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-medium">Header Diskon</Label>
                    <div class="flex gap-2">
                        <Select
                            :model-value="form.header_discount_type ?? ''"
                            @update:model-value="(v) => (form.header_discount_type = v || null)"
                        >
                            <SelectTrigger class="h-10 w-24 rounded-xl shrink-0"><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="rp">Rp</SelectItem>
                                <SelectItem value="percent">%</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            v-model="form.header_discount_value"
                            type="number"
                            step="0.01"
                            min="0"
                            class="h-10 flex-1 rounded-xl font-mono"
                            :disabled="!form.header_discount_type"
                        />
                    </div>
                </div>

                <!-- Catatan: full width -->
                <div class="space-y-1.5 sm:col-span-3">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" class="w-full rounded-xl" />
                </div>
            </div>

            <!-- Customer warning banners -->
            <div v-if="hasProblemOutlet" class="mt-3 rounded-xl bg-red-50 ring-1 ring-red-200 p-3 text-xs flex items-start gap-2">
                <AlertTriangle class="size-4 text-red-700 mt-0.5" />
                <p class="text-red-900">
                    <strong>problem_outlet</strong> — SO baru akan di-block saat submit. Hubungi admin untuk clear flag.
                </p>
            </div>
            <div v-if="hasSlowPayer && !hasProblemOutlet" class="mt-3 rounded-xl bg-warning-soft ring-1 ring-warning/30 p-3 text-xs flex items-start gap-2">
                <AlertTriangle class="size-4 text-amber-700 mt-0.5" />
                <p class="text-amber-900">
                    <strong>slow_payer</strong> — pastikan customer ini sanggup bayar.
                </p>
            </div>
            <div v-if="selectedCustomer" class="mt-3 text-xs text-muted-foreground">
                Credit limit: <strong class="font-mono">{{ fmtRp(selectedCustomer.credit_limit) }}</strong>
                · Payment term: <strong>{{ selectedCustomer.payment_term_days }} hari</strong>
            </div>
        </section>

        <!-- Items -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-foreground/5 px-5 py-3.5 flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items</p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="rounded-full"
                    :disabled="!form.customer_id || productCatalog.length === 0"
                    @click="addItem"
                >
                    <Plus class="size-3.5" /> Tambah Item
                </Button>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-3 min-w-[240px]">Produk</th>
                            <th class="text-left py-2.5 px-2 min-w-[120px]">Satuan</th>
                            <th class="text-right py-2.5 px-2 w-[80px]">Qty</th>
                            <th class="text-right py-2.5 px-2 w-[110px]">Harga</th>
                            <th class="text-left py-2.5 px-2 min-w-[170px]">Diskon</th>
                            <th class="text-center py-2.5 px-2 w-[60px]">Bonus</th>
                            <th class="text-right py-2.5 px-3 w-[130px]">Subtotal</th>
                            <th class="py-2.5 px-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-if="form.items.length === 0">
                            <td colspan="8" class="py-8 text-center text-muted-foreground text-xs">
                                {{ form.customer_id ? 'Klik "Tambah Item" untuk mulai.' : 'Pilih customer dulu.' }}
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-foreground/2.5 transition-colors align-top">
                            <td class="py-2 px-3">
                                <SearchableSelect
                                    :model-value="row.product_id"
                                    :options="productCatalog"
                                    :option-value="(p) => p.product_id"
                                    :option-label="(p) => `${p.name} (${p.sku})`"
                                    :fallback-label="row._product_name ?? ''"
                                    placeholder="Ketik nama / SKU produk…"
                                    empty-text="Produk tidak ditemukan."
                                    trigger-class="h-9 rounded-lg"
                                    @update:model-value="(v) => onProductChange(idx, v)"
                                >
                                    <template #option="{ option }">
                                        <p class="text-sm font-medium truncate">{{ option.name }}</p>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ option.sku }}</p>
                                    </template>
                                </SearchableSelect>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row._option_key ?? ''"
                                    @update:model-value="(v) => onOptionChange(idx, v)"
                                >
                                    <SelectTrigger class="h-9 w-full rounded-lg"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="o in optionsForRow(row)"
                                            :key="`${o.supplier_id}|${o.product_unit_id}`"
                                            :value="`${o.supplier_id}|${o.product_unit_id}`"
                                        >
                                            {{ o.unit_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty" type="number" min="1" class="h-9 w-full rounded-lg text-right font-mono" />
                            </td>
                            <td class="py-2 px-2 text-right font-mono text-xs pt-4">
                                {{ row.is_bonus ? '—' : fmtRp(row._unit_price ?? 0) }}
                            </td>
                            <td class="py-2 px-2">
                                <div class="flex gap-1.5">
                                    <Select
                                        :model-value="row.discount_type ?? ''"
                                        :disabled="row.is_bonus"
                                        @update:model-value="(v) => setDiscountType(row, v)"
                                    >
                                        <SelectTrigger class="h-9 w-16 rounded-lg shrink-0"><SelectValue placeholder="—" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="percent">%</SelectItem>
                                            <SelectItem value="rp">Rp</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        v-model="row.discount_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-9 flex-1 rounded-lg text-right font-mono"
                                        :disabled="row.is_bonus || !row.discount_type"
                                        placeholder="0"
                                    />
                                </div>
                            </td>
                            <td class="py-2 px-2 text-center pt-3.5">
                                <input v-model="row.is_bonus" type="checkbox" class="size-4 accent-brand" />
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-xs pt-4">
                                {{ fmtRp(lineSubtotal(row)) }}
                            </td>
                            <td class="py-2 px-2 text-center pt-3">
                                <button
                                    type="button"
                                    class="size-8 rounded-lg hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors"
                                    @click="removeItem(idx)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Totals -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="text-xs text-muted-foreground space-y-1">
                    <p>💡 Harga otomatis dari paket harga produk. Diskon per-item bisa % atau Rp.</p>
                    <p>Item bonus: harga otomatis 0, dipotong dari bonus_pool saat reservasi.</p>
                    <p><strong>Cashback</strong> = potongan tambahan tingkat SO, di luar diskon item & header.</p>
                </div>
                <div class="space-y-2 sm:justify-self-end w-full sm:max-w-xs">
                    <div class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-mono">{{ fmtRp(subtotal) }}</span>
                    </div>
                    <div v-if="itemDiscountTotal > 0" class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Diskon Item</span>
                        <span class="font-mono text-amber-700">− {{ fmtRp(itemDiscountTotal) }}</span>
                    </div>
                    <div v-if="headerDiscAmount > 0" class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Diskon Header</span>
                        <span class="font-mono text-amber-700">− {{ fmtRp(headerDiscAmount) }}</span>
                    </div>

                    <!-- Cashback input -->
                    <div class="flex items-center justify-between gap-3 pt-1">
                        <span class="text-sm text-muted-foreground shrink-0">Cashback</span>
                        <div class="relative w-36">
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">Rp</span>
                            <Input
                                v-model="form.cashback"
                                type="number"
                                min="0"
                                step="0.01"
                                class="h-9 rounded-lg text-right font-mono pl-8"
                                placeholder="0"
                            />
                        </div>
                    </div>
                    <div v-if="cashbackAmount > 0" class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Cashback</span>
                        <span class="font-mono text-emerald-700">− {{ fmtRp(cashbackAmount) }}</span>
                    </div>

                    <div class="flex justify-between gap-12 text-base pt-2 border-t border-foreground/5 font-bold">
                        <span>TOTAL</span>
                        <span class="font-mono">{{ fmtRp(total) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button
                type="submit"
                size="default"
                class="rounded-full bg-brand text-white hover:bg-brand-dark"
                :disabled="form.processing || form.items.length === 0 || !form.customer_id"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
