<script setup>
import { Loader2, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import CurrencyInput from '@/Components/Shared/CurrencyInput.vue';
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
    poList: { type: Array, default: () => [] }, // open PO list (Create mode)
    suppliers: { type: Array, default: () => [] }, // supplier aktif (mode langsung)
    selectedPo: { type: Object, default: null },
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    submitLabel: { type: String, default: 'Simpan Draft' },
});

const emit = defineEmits(['submit']);

// value = enum backend (jangan diubah); label = tampilan Bahasa Indonesia.
const CONDITIONS = [
    { value: 'good', label: 'Baik' },
    { value: 'damaged', label: 'Rusak' },
    { value: 'mixed', label: 'Campuran' },
];

// Mode penerimaan: 'po' (dari PO) atau 'direct' (langsung tanpa PO).
// Di edit, mode terkunci mengikuti apakah GRN punya purchase_order_id.
const receiptMode = computed({
    get: () => props.form.__mode,
    set: (v) => {
        if (props.mode === 'edit') return;
        props.form.__mode = v;
        // Reset saat ganti mode.
        props.form.purchase_order_id = null;
        props.form.supplier_id = null;
        props.form.__po = null;
        props.form.__supplierProducts = [];
        props.form.items = [];
    },
});

const isDirect = computed(() => receiptMode.value === 'direct');

/* ─────────── Mode: Dari PO ─────────── */
async function loadPo(poId) {
    if (!poId) {
        props.form.__po = null;
        props.form.items = [];
        return;
    }
    try {
        const res = await fetch(route('grns.po-details', poId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        props.form.__po = data.po;
        if (props.mode === 'create') {
            props.form.items = (data.po?.items ?? [])
                .filter((it) => it.qty_remaining > 0 || it.bonus_qty > it.bonus_qty_received)
                .map((it) => ({
                    po_item_id: it.id,
                    product_id: it.product_id,
                    product_unit_id: it.product_unit_id,
                    product_name: it.product_name,
                    product_sku: it.product_sku,
                    unit_name: it.unit_name,
                    qty_ordered: it.qty_ordered,
                    qty_received_already: it.qty_received,
                    qty_remaining: it.qty_remaining,
                    bonus_qty: it.bonus_qty,
                    bonus_qty_received_already: it.bonus_qty_received,
                    batch_code: '',
                    production_date: '',
                    expired_date: '',
                    qty_reguler: it.qty_remaining,
                    qty_bonus: Math.max(0, it.bonus_qty - it.bonus_qty_received),
                    qty_damaged: 0,
                    cost_price: it.unit_net_cost,
                    condition: 'good',
                    notes: '',
                }));
        }
    } catch {
        props.form.__po = null;
    }
}

function onPoChange(poId) {
    props.form.purchase_order_id = poId ? Number(poId) : null;
    loadPo(props.form.purchase_order_id);
}

watch(
    () => props.form.purchase_order_id,
    (val) => {
        // Edit mode dari PO: PO sudah ter-set; load metadata sekali.
        if (props.mode === 'edit' && val && !props.form.__po) {
            loadPo(val);
        }
    },
    { immediate: true },
);

/* ─────────── Mode: Langsung (tanpa PO) ─────────── */
const supplierProducts = computed(() => props.form.__supplierProducts ?? []);

async function loadSupplierProducts(supplierId) {
    if (!supplierId) {
        props.form.__supplierProducts = [];
        return;
    }
    try {
        const res = await fetch(route('grns.supplier-products', supplierId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        props.form.__supplierProducts = data.products ?? [];
    } catch {
        props.form.__supplierProducts = [];
    }
}

function onSupplierChange(supplierId) {
    props.form.supplier_id = supplierId ? Number(supplierId) : null;
    props.form.items = [];
    loadSupplierProducts(props.form.supplier_id);
}

// Edit mode langsung: muat ulang daftar produk supplier sekali (untuk dropdown).
watch(
    () => props.form.supplier_id,
    (val) => {
        if (props.mode === 'edit' && isDirect.value && val && !supplierProducts.value.length) {
            loadSupplierProducts(val);
        }
    },
    { immediate: true },
);

function addDirectItem() {
    props.form.items.push({
        po_item_id: null,
        product_id: null,
        product_unit_id: null,
        product_name: '',
        product_sku: '',
        unit_name: '',
        batch_code: '',
        production_date: '',
        expired_date: '',
        qty_reguler: 1,
        qty_delivery_note: 1,
        qty_bonus: 0,
        qty_damaged: 0,
        cost_price: 0,
        condition: 'good',
        notes: '',
    });
}

// Pending penerimaan langsung = surat jalan − diterima (min 0). Untuk indikator
// live di form.
function directPending(row) {
    const sj = Number(row.qty_delivery_note) || 0;
    const reg = Number(row.qty_reguler) || 0;
    return Math.max(0, sj - reg);
}

function removeItem(idx) {
    props.form.items.splice(idx, 1);
}

function onDirectProductChange(idx, productId) {
    const row = props.form.items[idx];
    const id = productId ? Number(productId) : null;
    row.product_id = id;
    row.product_unit_id = null;
    if (!id) return;
    const prod = supplierProducts.value.find((p) => p.product_id === id);
    if (prod) {
        row.product_name = prod.name;
        row.product_sku = prod.sku;
        const firstUnit = prod.units[0];
        if (firstUnit) {
            row.product_unit_id = firstUnit.id;
            row.unit_name = firstUnit.name;
            row.cost_price = firstUnit.cost_price ?? 0;
        }
    }
}

function unitsForRow(idx) {
    const row = props.form.items[idx];
    if (!row?.product_id) return [];
    const prod = supplierProducts.value.find((p) => p.product_id === row.product_id);
    return prod?.units ?? [];
}

function onDirectUnitChange(idx, unitId) {
    const row = props.form.items[idx];
    const id = unitId ? Number(unitId) : null;
    row.product_unit_id = id;
    const units = unitsForRow(idx);
    const u = units.find((x) => x.id === id);
    if (u) {
        row.unit_name = u.name;
        row.cost_price = u.cost_price ?? row.cost_price;
    }
}

/* ─────────── Ringkasan & validasi ─────────── */
const totalReguler = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_reguler) || 0), 0));
const totalBonus = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_bonus) || 0), 0));
const totalDamaged = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_damaged) || 0), 0));

function hasOverReceive(row) {
    if (props.mode === 'edit' || isDirect.value) return false;
    const newCum = (Number(row.qty_received_already) || 0) + (Number(row.qty_reguler) || 0);
    return newCum > (Number(row.qty_ordered) || 0);
}

const canSubmit = computed(() => {
    if (props.form.items.length === 0) return false;
    if (isDirect.value) return !!props.form.supplier_id;
    return !!props.form.purchase_order_id;
});
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Toggle mode (hanya saat create) -->
        <section v-if="mode === 'create'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-2 mb-4">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    :class="[
                        'rounded-xl px-4 py-3 text-left transition-colors',
                        !isDirect ? 'bg-brand text-white' : 'bg-muted/50 hover:bg-muted',
                    ]"
                    @click="receiptMode = 'po'"
                >
                    <p class="text-sm font-semibold">Dari Purchase Order</p>
                    <p class="text-[11px] mt-0.5" :class="!isDirect ? 'text-white/80' : 'text-muted-foreground'">
                        Terima barang atas PO yang sudah di-approve.
                    </p>
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded-xl px-4 py-3 text-left transition-colors',
                        isDirect ? 'bg-brand text-white' : 'bg-muted/50 hover:bg-muted',
                    ]"
                    @click="receiptMode = 'direct'"
                >
                    <p class="text-sm font-semibold">Penerimaan Langsung</p>
                    <p class="text-[11px] mt-0.5" :class="isDirect ? 'text-white/80' : 'text-muted-foreground'">
                        Tanpa PO — input supplier & barang sendiri.
                    </p>
                </button>
            </div>
        </section>

        <!-- Header -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3.5">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <!-- Sumber: PO atau Supplier -->
                <div class="space-y-1 sm:col-span-2">
                    <template v-if="!isDirect">
                        <Label class="text-xs font-medium">Purchase Order *</Label>
                        <Select
                            v-if="mode === 'create'"
                            :model-value="form.purchase_order_id ? String(form.purchase_order_id) : ''"
                            @update:model-value="onPoChange"
                        >
                            <SelectTrigger class="h-10 w-full rounded-xl">
                                <SelectValue placeholder="Pilih PO yang open (approved / partial)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="po in poList" :key="po.id" :value="String(po.id)">
                                    {{ po.po_number }} · {{ po.supplier?.name }}
                                    <span class="text-[11px] text-muted-foreground ml-1">({{ po.status }})</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-else class="h-10 flex items-center font-mono text-sm">
                            {{ form.__po?.po_number ?? '—' }}
                        </div>
                        <p v-if="form.errors.purchase_order_id" class="text-xs text-destructive">{{ form.errors.purchase_order_id }}</p>
                    </template>

                    <template v-else>
                        <Label class="text-xs font-medium">Supplier *</Label>
                        <Select
                            v-if="mode === 'create'"
                            :model-value="form.supplier_id ? String(form.supplier_id) : ''"
                            @update:model-value="onSupplierChange"
                        >
                            <SelectTrigger class="h-10 w-full rounded-xl">
                                <SelectValue placeholder="Pilih supplier" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">
                                    {{ s.name }} <span class="text-xs text-muted-foreground">({{ s.code }})</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-else class="h-10 flex items-center text-sm">
                            {{ form.__supplierName ?? '—' }}
                        </div>
                        <p v-if="form.errors.supplier_id" class="text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                    </template>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal Terima *</Label>
                    <Input v-model="form.received_date" type="date" required class="h-10 rounded-xl" />
                    <p v-if="form.errors.received_date" class="text-xs text-destructive">{{ form.errors.received_date }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">No. Surat Jalan Supplier</Label>
                    <Input v-model="form.supplier_delivery_no" class="h-10 rounded-xl font-mono" />
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" class="rounded-xl" />
                </div>
            </div>
        </section>

        <!-- Items -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3.5 flex items-center justify-between border-b border-foreground/5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items ({{ form.items.length }})</p>
                <div class="flex items-center gap-3">
                    <p class="text-xs text-muted-foreground">
                        Reg: <strong class="font-mono text-foreground">{{ totalReguler }}</strong>
                        · Bonus: <strong class="font-mono text-foreground">{{ totalBonus }}</strong>
                        · Rusak: <strong class="font-mono text-foreground">{{ totalDamaged }}</strong>
                    </p>
                    <Button
                        v-if="isDirect && mode === 'create'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="rounded-full"
                        :disabled="!form.supplier_id || supplierProducts.length === 0"
                        @click="addDirectItem"
                    >
                        <Plus class="size-3.5" /> Tambah Item
                    </Button>
                </div>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-4 font-semibold min-w-[220px]">Produk</th>
                            <th v-if="isDirect" class="text-left py-2.5 px-2 font-semibold">Unit</th>
                            <th v-else class="text-right py-2.5 px-2 font-semibold">PO Order</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Batch *</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Prod</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Exp</th>
                            <th v-if="isDirect" class="text-right py-2.5 px-2 font-semibold">Surat Jalan</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Reg</th>
                            <th v-if="isDirect" class="text-right py-2.5 px-2 font-semibold">Pending</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Bonus</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Rusak</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Cost</th>
                            <th class="text-left py-2.5 px-2 font-semibold">Kondisi</th>
                            <th v-if="isDirect && mode === 'create'" class="py-2.5 px-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-if="form.items.length === 0">
                            <td :colspan="isDirect ? 14 : 11" class="py-10 text-center text-muted-foreground text-xs">
                                <template v-if="isDirect">
                                    {{ form.supplier_id ? 'Klik "Tambah Item" untuk mulai.' : 'Pilih supplier dulu.' }}
                                </template>
                                <template v-else>
                                    {{ form.purchase_order_id ? 'Items akan muncul setelah PO dipilih.' : 'Pilih PO dulu.' }}
                                </template>
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-foreground/2.5 transition-colors">
                            <!-- Produk -->
                            <td class="py-2 px-4">
                                <template v-if="isDirect && mode === 'create'">
                                    <SearchableSelect
                                        :model-value="row.product_id"
                                        :options="supplierProducts"
                                        :option-value="(p) => p.product_id"
                                        :option-label="(p) => `${p.name} (${p.sku})`"
                                        placeholder="Ketik nama / SKU produk…"
                                        empty-text="Tidak ada produk cocok."
                                        trigger-class="h-9 rounded-lg"
                                        @update:model-value="(v) => onDirectProductChange(idx, v)"
                                    >
                                        <template #option="{ option }">
                                            <p class="text-sm font-medium truncate">{{ option.name }}</p>
                                            <p class="text-[11px] text-muted-foreground font-mono">{{ option.sku }}</p>
                                        </template>
                                    </SearchableSelect>
                                    <p v-if="form.errors[`items.${idx}.product_id`]" class="text-[11px] text-destructive mt-0.5">
                                        {{ form.errors[`items.${idx}.product_id`] }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="font-medium text-xs">{{ row.product_name }}</p>
                                    <p class="text-[11px] text-muted-foreground font-mono">{{ row.product_sku }} · {{ row.unit_name }}</p>
                                </template>
                            </td>

                            <!-- Unit (direct) atau PO Order (po) -->
                            <td v-if="isDirect" class="py-2 px-2">
                                <Select
                                    v-if="mode === 'create'"
                                    :model-value="row.product_unit_id ? String(row.product_unit_id) : ''"
                                    @update:model-value="(v) => onDirectUnitChange(idx, v)"
                                >
                                    <SelectTrigger class="h-9 w-full rounded-lg">
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in unitsForRow(idx)" :key="u.id" :value="String(u.id)">
                                            {{ u.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <span v-else class="text-xs font-mono">{{ row.unit_name }}</span>
                            </td>
                            <td v-else class="py-2 px-2 text-right text-xs font-mono">
                                <span class="font-medium">{{ row.qty_ordered }}</span>
                                <span v-if="row.qty_received_already > 0" class="block text-[11px] text-muted-foreground">
                                    rcvd {{ row.qty_received_already }} · sisa {{ row.qty_remaining }}
                                </span>
                            </td>

                            <td class="py-2 px-2">
                                <Input v-model="row.batch_code" required class="h-9 rounded-lg text-xs font-mono" />
                                <p v-if="form.errors[`items.${idx}.batch_code`]" class="text-[11px] text-destructive">
                                    {{ form.errors[`items.${idx}.batch_code`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.production_date" type="date" class="h-9 rounded-lg text-xs" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.expired_date" type="date" class="h-9 rounded-lg text-xs" />
                            </td>
                            <td v-if="isDirect" class="py-2 px-2">
                                <Input
                                    v-model="row.qty_delivery_note"
                                    type="number"
                                    min="0"
                                    class="h-9 rounded-lg text-right font-mono"
                                />
                                <p v-if="form.errors[`items.${idx}.qty_delivery_note`]" class="text-[11px] text-destructive">
                                    {{ form.errors[`items.${idx}.qty_delivery_note`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Input
                                    v-model="row.qty_reguler"
                                    type="number"
                                    min="0"
                                    :class="['h-9 rounded-lg text-right font-mono', hasOverReceive(row) ? 'ring-2 ring-amber-300' : '']"
                                />
                                <p v-if="hasOverReceive(row)" class="text-[11px] text-amber-700">Over-receive</p>
                            </td>
                            <td v-if="isDirect" class="py-2 px-2 text-right font-mono text-xs">
                                <span v-if="directPending(row) > 0" class="text-amber-700">{{ directPending(row) }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty_bonus" type="number" min="0" class="h-9 rounded-lg text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty_damaged" type="number" min="0" class="h-9 rounded-lg text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <CurrencyInput v-model="row.cost_price" class="h-9 rounded-lg font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Select v-model="row.condition">
                                    <SelectTrigger class="h-9 w-full rounded-lg">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in CONDITIONS" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td v-if="isDirect && mode === 'create'" class="py-2 px-2 text-center">
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

        <!-- Discrepancy note + actions -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <Label class="text-xs font-medium">Catatan Discrepancy (jika short-ship / rusak)</Label>
            <Textarea v-model="form.discrepancy_notes" rows="2" class="mt-1.5 rounded-xl" />
        </section>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button
                type="submit"
                size="default"
                class="rounded-full bg-brand text-white hover:bg-brand-dark"
                :disabled="form.processing || !canSubmit"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
