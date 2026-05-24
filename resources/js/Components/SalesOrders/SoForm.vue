<script setup>
import { AlertTriangle, Loader2, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
const productMatrix = ref([]); // list dari endpoint customer-products

const customerTags = computed(() => selectedCustomer.value?.tags ?? []);
const hasProblemOutlet = computed(() => customerTags.value.includes('problem_outlet'));
const hasSlowPayer = computed(() => customerTags.value.includes('slow_payer'));

async function loadCustomer(customerId) {
    const cust = props.customers.find((c) => c.id === Number(customerId));
    selectedCustomer.value = cust ?? null;

    if (!customerId) {
        productMatrix.value = [];
        return;
    }
    if (cust && !props.form.payment_term_days) {
        props.form.payment_term_days = cust.payment_term_days ?? null;
    }

    try {
        const res = await fetch(route('sales-orders.customer-products', customerId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        productMatrix.value = data.products ?? [];
    } catch {
        productMatrix.value = [];
    }
}

function onCustomerChange(v) {
    props.form.customer_id = v ? Number(v) : null;
    props.form.items = [];
    loadCustomer(props.form.customer_id);
}

function addItem() {
    props.form.items.push({
        product_id: null,
        product_unit_id: null,
        qty: 1,
        discount_z1_pct: 0,
        discount_z2_pct: 0,
        is_bonus: false,
        notes: '',
        // helper UI state
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
    row._unit_price = 0;
    row._product_name = null;
    if (!id) return;
    const prod = productMatrix.value.find((p) => p.product_id === id);
    if (prod) {
        row._product_name = prod.name;
        const baseUnit = prod.units.find((u) => u.level === 'KCL') ?? prod.units[0];
        if (baseUnit) {
            row.product_unit_id = baseUnit.id;
            row._unit_price = baseUnit.price;
        }
    }
}

function onUnitChange(idx, unitId) {
    const row = props.form.items[idx];
    row.product_unit_id = unitId ? Number(unitId) : null;
    if (!row.product_id) return;
    const prod = productMatrix.value.find((p) => p.product_id === row.product_id);
    const unit = prod?.units.find((u) => u.id === Number(unitId));
    if (unit) row._unit_price = unit.price;
}

function unitsForRow(row) {
    if (!row.product_id) return [];
    const prod = productMatrix.value.find((p) => p.product_id === row.product_id);
    return prod?.units ?? [];
}

function lineNet(row) {
    if (row.is_bonus) return 0;
    const price = Number(row._unit_price) || 0;
    const z1 = Number(row.discount_z1_pct) || 0;
    const z2 = Number(row.discount_z2_pct) || 0;
    return Math.round(price * (1 - z1 / 100) * (1 - z2 / 100) * 100) / 100;
}

function lineSubtotal(row) {
    return Math.round(lineNet(row) * (Number(row.qty) || 0) * 100) / 100;
}

const subtotal = computed(() => props.form.items.reduce((s, r) => s + lineSubtotal(r), 0));
const headerDiscAmount = computed(() => {
    const type = props.form.header_discount_type;
    const val = Number(props.form.header_discount_value) || 0;
    if (type === 'rp') return Math.min(subtotal.value, val);
    if (type === 'percent') return Math.round(subtotal.value * val) / 100;
    return 0;
});
const total = computed(() => Math.max(0, subtotal.value - headerDiscAmount.value));

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v) || 0);
}

// Lazy-init kalau Edit mode (customer sudah ter-set)
watch(
    () => props.form.customer_id,
    (val) => {
        if (val && !selectedCustomer.value) {
            loadCustomer(val);
            // Hydrate _unit_price untuk existing items
            setTimeout(() => {
                props.form.items.forEach((row, i) => {
                    if (row.product_id && row.product_unit_id) {
                        const prod = productMatrix.value.find((p) => p.product_id === row.product_id);
                        const unit = prod?.units.find((u) => u.id === row.product_unit_id);
                        if (unit) row._unit_price = unit.price;
                        if (prod) row._product_name = prod.name;
                    }
                });
            }, 200);
        }
    },
    { immediate: true },
);
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Header -->
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1 sm:col-span-2">
                    <Label class="text-xs font-medium">Customer *</Label>
                    <Select
                        :model-value="form.customer_id ? String(form.customer_id) : ''"
                        @update:model-value="onCustomerChange"
                    >
                        <SelectTrigger class="h-9">
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
                <div v-if="salesUsers.length > 0" class="space-y-1">
                    <Label class="text-xs font-medium">Sales (atas nama)</Label>
                    <Select
                        :model-value="form.sales_id ? String(form.sales_id) : ''"
                        @update:model-value="(v) => (form.sales_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="(diri sendiri)" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="u in salesUsers" :key="u.id" :value="String(u.id)">
                                {{ u.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal SO *</Label>
                    <Input v-model="form.so_date" type="date" required class="h-9" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">ETA Pengiriman</Label>
                    <Input v-model="form.eta_date" type="date" class="h-9" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Payment Term (hari)</Label>
                    <Input v-model="form.payment_term_days" type="number" min="0" max="365" class="h-9 font-mono" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Header Diskon</Label>
                    <div class="flex gap-2">
                        <Select
                            :model-value="form.header_discount_type ?? ''"
                            @update:model-value="(v) => (form.header_discount_type = v || null)"
                        >
                            <SelectTrigger class="h-9 w-20"><SelectValue placeholder="—" /></SelectTrigger>
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
                            class="h-9 font-mono flex-1"
                            :disabled="!form.header_discount_type"
                        />
                    </div>
                </div>
                <div class="space-y-1 sm:col-span-3">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
            </div>

            <!-- Customer warning banners -->
            <div v-if="hasProblemOutlet" class="mt-3 rounded-md bg-red-50 ring-1 ring-red-200 p-3 text-xs flex items-start gap-2">
                <AlertTriangle class="size-4 text-red-700 mt-0.5" />
                <p class="text-red-900">
                    <strong>problem_outlet</strong> — SO baru akan di-block saat submit. Hubungi admin untuk clear flag.
                </p>
            </div>
            <div v-if="hasSlowPayer && !hasProblemOutlet" class="mt-3 rounded-md bg-warning-soft ring-1 ring-warning/30 p-3 text-xs flex items-start gap-2">
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
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items</p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="!form.customer_id || productMatrix.length === 0"
                    @click="addItem"
                >
                    <Plus class="size-3.5" /> Tambah Item
                </Button>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2 px-3 w-[26%]">Produk</th>
                            <th class="text-left py-2 px-2 w-[10%]">Unit</th>
                            <th class="text-right py-2 px-2 w-[8%]">Qty</th>
                            <th class="text-right py-2 px-2 w-[10%]">Harga</th>
                            <th class="text-right py-2 px-2 w-[7%]">Z1%</th>
                            <th class="text-right py-2 px-2 w-[7%]">Z2%</th>
                            <th class="text-center py-2 px-2 w-[6%]">Bonus</th>
                            <th class="text-right py-2 px-3 w-[14%]">Subtotal</th>
                            <th class="py-2 px-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-if="form.items.length === 0">
                            <td colspan="9" class="py-8 text-center text-muted-foreground text-xs">
                                {{ form.customer_id ? 'Klik "Tambah Item" untuk mulai.' : 'Pilih customer dulu.' }}
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-muted/20">
                            <td class="py-2 px-3">
                                <Select
                                    :model-value="row.product_id ? String(row.product_id) : ''"
                                    @update:model-value="(v) => onProductChange(idx, v)"
                                >
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="Pilih produk" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="p in productMatrix" :key="p.product_id" :value="String(p.product_id)">
                                            {{ p.name }}
                                            <span class="text-[10px] text-muted-foreground font-mono ml-1">{{ p.sku }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row.product_unit_id ? String(row.product_unit_id) : ''"
                                    @update:model-value="(v) => onUnitChange(idx, v)"
                                >
                                    <SelectTrigger class="h-8"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in unitsForRow(row)" :key="u.id" :value="String(u.id)">
                                            {{ u.name }} ({{ u.level }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty" type="number" min="1" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2 text-right font-mono text-xs">
                                {{ row.is_bonus ? '—' : fmtRp(row._unit_price ?? 0) }}
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.discount_z1_pct" type="number" min="0" max="100" step="0.01" class="h-8 text-right font-mono" :disabled="row.is_bonus" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.discount_z2_pct" type="number" min="0" max="100" step="0.01" class="h-8 text-right font-mono" :disabled="row.is_bonus" />
                            </td>
                            <td class="py-2 px-2 text-center">
                                <input v-model="row.is_bonus" type="checkbox" class="size-4" />
                            </td>
                            <td class="py-2 px-3 text-right font-mono text-xs">
                                {{ fmtRp(lineSubtotal(row)) }}
                            </td>
                            <td class="py-2 px-2 text-center">
                                <button type="button" class="text-muted-foreground hover:text-destructive" @click="removeItem(idx)">
                                    <Trash2 class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Totals -->
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="text-xs text-muted-foreground">
                    <p>💡 Harga otomatis dari tier customer. Sales tidak boleh override harga unit — pakai Z1/Z2 untuk diskon.</p>
                    <p class="mt-1">Item bonus: harga otomatis 0, dipotong dari bonus_pool saat reservation.</p>
                </div>
                <div class="space-y-2 sm:justify-self-end">
                    <div class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="font-mono">{{ fmtRp(subtotal) }}</span>
                    </div>
                    <div v-if="headerDiscAmount > 0" class="flex justify-between gap-12 text-sm">
                        <span class="text-muted-foreground">Diskon Header</span>
                        <span class="font-mono">− {{ fmtRp(headerDiscAmount) }}</span>
                    </div>
                    <div class="flex justify-between gap-12 text-base pt-2 border-t border-border/70 font-bold">
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
                variant="secondary"
                size="default"
                :disabled="form.processing || form.items.length === 0 || !form.customer_id"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
