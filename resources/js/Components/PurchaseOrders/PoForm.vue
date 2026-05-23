<script setup>
import { Loader2, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
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
    suppliers: { type: Array, required: true },
    submitLabel: { type: String, default: 'Simpan' },
});

const emit = defineEmits(['submit']);

const supplierProducts = computed(() => props.form.__supplierProducts ?? []);

async function onSupplierChange(supplierId) {
    props.form.supplier_id = supplierId ? Number(supplierId) : null;
    props.form.items = [];
    props.form.__supplierProducts = [];
    if (!supplierId) return;
    try {
        const res = await fetch(route('purchase-orders.supplier-products', supplierId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        props.form.__supplierProducts = data.products ?? [];
        const sup = props.suppliers.find((s) => s.id === Number(supplierId));
        if (sup && !props.form.payment_term_days) {
            props.form.payment_term_days = sup.payment_term_days ?? null;
        }
    } catch {
        props.form.__supplierProducts = [];
    }
}

function addItem() {
    props.form.items.push({
        product_id: null,
        product_unit_id: null,
        qty_ordered: 1,
        bonus_qty: 0,
        cost_price: 0,
        discount_z1_pct: 0,
        discount_z2_pct: 0,
        notes: '',
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
    if (!id) return;
    const prod = supplierProducts.value.find((p) => p.product_id === id);
    if (prod) {
        row.cost_price = prod.default_cost_price ?? 0;
        // Default unit = base (KCL) atau yang pertama tersedia
        const baseUnit = prod.units.find((u) => u.level === 'KCL') ?? prod.units[0];
        if (baseUnit) row.product_unit_id = baseUnit.id;
    }
}

function unitsForRow(idx) {
    const row = props.form.items[idx];
    if (!row?.product_id) return [];
    const prod = supplierProducts.value.find((p) => p.product_id === row.product_id);
    return prod?.units ?? [];
}

function lineNet(row) {
    const cost = Number(row.cost_price) || 0;
    const z1 = Number(row.discount_z1_pct) || 0;
    const z2 = Number(row.discount_z2_pct) || 0;
    return Math.round(cost * (1 - z1 / 100) * (1 - z2 / 100) * 100) / 100;
}

function lineSubtotal(row) {
    return Math.round(lineNet(row) * (Number(row.qty_ordered) || 0) * 100) / 100;
}

const subtotal = computed(() =>
    props.form.items.reduce((sum, r) => sum + lineSubtotal(r), 0),
);

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

// Lazy-init supplier products bila editing mode (supplier sudah ter-pre-fill)
watch(
    () => props.form.supplier_id,
    (newVal, oldVal) => {
        if (newVal && newVal !== oldVal && !supplierProducts.value.length) {
            onSupplierChange(newVal);
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
                    <Label class="text-xs font-medium">Supplier *</Label>
                    <Select
                        :model-value="form.supplier_id ? String(form.supplier_id) : ''"
                        @update:model-value="onSupplierChange"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Pilih supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">
                                {{ s.name }} <span class="text-xs text-muted-foreground">({{ s.code }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.supplier_id" class="text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Payment Term (hari)</Label>
                    <Input v-model="form.payment_term_days" type="number" min="0" max="365" class="h-9 font-mono" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal PO *</Label>
                    <Input v-model="form.po_date" type="date" required class="h-9" />
                    <p v-if="form.errors.po_date" class="text-xs text-destructive">{{ form.errors.po_date }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">ETA</Label>
                    <Input v-model="form.eta_date" type="date" class="h-9" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Header Diskon</Label>
                    <div class="flex gap-2">
                        <Select
                            :model-value="form.header_discount_type ?? ''"
                            @update:model-value="(v) => (form.header_discount_type = v || null)"
                        >
                            <SelectTrigger class="h-9 w-24">
                                <SelectValue placeholder="—" />
                            </SelectTrigger>
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
        </section>

        <!-- Items -->
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items</p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="!form.supplier_id || supplierProducts.length === 0"
                    @click="addItem"
                >
                    <Plus class="size-3.5" />
                    Tambah Item
                </Button>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2 px-3 w-[28%]">Produk</th>
                            <th class="text-left py-2 px-2 w-[12%]">Unit</th>
                            <th class="text-right py-2 px-2 w-[8%]">Qty</th>
                            <th class="text-right py-2 px-2 w-[8%]">Bonus</th>
                            <th class="text-right py-2 px-2 w-[12%]">Harga</th>
                            <th class="text-right py-2 px-2 w-[8%]">Z1%</th>
                            <th class="text-right py-2 px-2 w-[8%]">Z2%</th>
                            <th class="text-right py-2 px-3 w-[14%]">Subtotal</th>
                            <th class="py-2 px-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-if="form.items.length === 0">
                            <td colspan="9" class="py-8 text-center text-muted-foreground text-xs">
                                {{ form.supplier_id ? 'Klik "Tambah Item" untuk mulai.' : 'Pilih supplier dulu.' }}
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
                                        <SelectItem v-for="p in supplierProducts" :key="p.product_id" :value="String(p.product_id)">
                                            {{ p.name }}
                                            <span class="text-[10px] text-muted-foreground ml-1 font-mono">{{ p.sku }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors[`items.${idx}.product_id`]" class="text-[11px] text-destructive mt-0.5">
                                    {{ form.errors[`items.${idx}.product_id`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row.product_unit_id ? String(row.product_unit_id) : ''"
                                    @update:model-value="(v) => (row.product_unit_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger class="h-8">
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in unitsForRow(idx)" :key="u.id" :value="String(u.id)">
                                            {{ u.name }} ({{ u.level }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty_ordered" type="number" min="1" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.bonus_qty" type="number" min="0" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.cost_price" type="number" min="0" step="0.01" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.discount_z1_pct" type="number" min="0" max="100" step="0.01" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.discount_z2_pct" type="number" min="0" max="100" step="0.01" class="h-8 text-right font-mono" />
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
                    <p>💡 Z1 & Z2 di-apply <strong>compound</strong> per item: <code>net = cost × (1−Z1) × (1−Z2)</code>.</p>
                    <p class="mt-1">Bonus tidak masuk subtotal — tracked terpisah saat GRN.</p>
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
                :disabled="form.processing || form.items.length === 0 || !form.supplier_id"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
