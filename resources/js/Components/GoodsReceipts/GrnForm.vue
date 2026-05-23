<script setup>
import { Loader2, PackageOpen } from '@lucide/vue';
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
    poList: { type: Array, default: () => [] }, // open PO list (Create mode)
    selectedPo: { type: Object, default: null },
    mode: { type: String, default: 'create' }, // 'create' | 'edit'
    submitLabel: { type: String, default: 'Simpan Draft' },
});

const emit = defineEmits(['submit']);

const CONDITIONS = ['good', 'damaged', 'mixed'];

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
        // Auto-populate items dari po items yang masih ada qty_remaining
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
        // Edit mode: PO sudah ter-set; load metadata sekali
        if (props.mode === 'edit' && val && !props.form.__po) {
            loadPo(val);
        }
    },
    { immediate: true },
);

const totalReguler = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_reguler) || 0), 0));
const totalBonus = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_bonus) || 0), 0));
const totalDamaged = computed(() => props.form.items.reduce((s, r) => s + (Number(r.qty_damaged) || 0), 0));

function hasOverReceive(row) {
    if (props.mode === 'edit') return false;
    const newCum = (Number(row.qty_received_already) || 0) + (Number(row.qty_reguler) || 0);
    return newCum > (Number(row.qty_ordered) || 0);
}
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Header -->
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1 sm:col-span-2">
                    <Label class="text-xs font-medium">Purchase Order *</Label>
                    <Select
                        v-if="mode === 'create'"
                        :model-value="form.purchase_order_id ? String(form.purchase_order_id) : ''"
                        @update:model-value="onPoChange"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Pilih PO yang open (approved / partial)" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="po in poList" :key="po.id" :value="String(po.id)">
                                {{ po.po_number }} · {{ po.supplier?.name }}
                                <span class="text-[10px] text-muted-foreground ml-1">({{ po.status }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-else class="h-9 flex items-center font-mono text-sm">
                        {{ form.__po?.po_number ?? '—' }}
                    </div>
                    <p v-if="form.errors.purchase_order_id" class="text-xs text-destructive">{{ form.errors.purchase_order_id }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal Terima *</Label>
                    <Input v-model="form.received_date" type="date" required class="h-9" />
                    <p v-if="form.errors.received_date" class="text-xs text-destructive">{{ form.errors.received_date }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">No. Surat Jalan Supplier</Label>
                    <Input v-model="form.supplier_delivery_no" class="h-9 font-mono" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Kendaraan</Label>
                    <Input v-model="form.supplier_vehicle_info" class="h-9" placeholder="BK 9303 FQ — Hino" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Supir Supplier</Label>
                    <Input v-model="form.supplier_driver_name" class="h-9" />
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
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items ({{ form.items.length }})</p>
                <p class="text-xs text-muted-foreground">
                    Reg: <strong class="font-mono">{{ totalReguler }}</strong>
                    · Bonus: <strong class="font-mono">{{ totalBonus }}</strong>
                    · Damaged: <strong class="font-mono">{{ totalDamaged }}</strong>
                </p>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2 px-3">Produk · Unit</th>
                            <th class="text-right py-2 px-2 w-[10%]">PO Order</th>
                            <th class="text-left py-2 px-2 w-[14%]">Batch *</th>
                            <th class="text-left py-2 px-2 w-[10%]">Prod</th>
                            <th class="text-left py-2 px-2 w-[10%]">Exp</th>
                            <th class="text-right py-2 px-2 w-[8%]">Reg</th>
                            <th class="text-right py-2 px-2 w-[7%]">Bonus</th>
                            <th class="text-right py-2 px-2 w-[7%]">Damage</th>
                            <th class="text-right py-2 px-2 w-[10%]">Cost</th>
                            <th class="text-left py-2 px-2 w-[8%]">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-if="form.items.length === 0">
                            <td colspan="10" class="py-8 text-center text-muted-foreground text-xs">
                                {{ form.purchase_order_id ? 'Items akan muncul setelah PO dipilih.' : 'Pilih PO dulu.' }}
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-muted/20">
                            <td class="py-2 px-3">
                                <p class="font-medium text-xs">{{ row.product_name }}</p>
                                <p class="text-[10px] text-muted-foreground font-mono">{{ row.product_sku }} · {{ row.unit_name }}</p>
                            </td>
                            <td class="py-2 px-2 text-right text-xs font-mono">
                                <span class="font-medium">{{ row.qty_ordered }}</span>
                                <span v-if="row.qty_received_already > 0" class="block text-[10px] text-muted-foreground">
                                    rcvd {{ row.qty_received_already }} · sisa {{ row.qty_remaining }}
                                </span>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.batch_code" required class="h-8 text-xs font-mono" />
                                <p v-if="form.errors[`items.${idx}.batch_code`]" class="text-[10px] text-destructive">
                                    {{ form.errors[`items.${idx}.batch_code`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.production_date" type="date" class="h-8 text-xs" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.expired_date" type="date" class="h-8 text-xs" />
                            </td>
                            <td class="py-2 px-2">
                                <Input
                                    v-model="row.qty_reguler"
                                    type="number"
                                    min="0"
                                    :class="['h-8 text-right font-mono', hasOverReceive(row) ? 'ring-2 ring-amber-300' : '']"
                                />
                                <p v-if="hasOverReceive(row)" class="text-[10px] text-amber-700">Over-receive</p>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty_bonus" type="number" min="0" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.qty_damaged" type="number" min="0" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.cost_price" type="number" min="0" step="0.01" class="h-8 text-right font-mono" />
                            </td>
                            <td class="py-2 px-2">
                                <Select v-model="row.condition">
                                    <SelectTrigger class="h-8">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in CONDITIONS" :key="c" :value="c">{{ c }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Discrepancy note + actions -->
        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <Label class="text-xs font-medium">Catatan Discrepancy (jika short-ship / damage)</Label>
            <Textarea v-model="form.discrepancy_notes" rows="2" class="mt-1" />
        </section>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button
                type="submit"
                variant="secondary"
                size="default"
                :disabled="form.processing || form.items.length === 0 || !form.purchase_order_id"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
