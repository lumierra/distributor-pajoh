<script setup>
import { Loader2, Plus, Trash2 } from '@lucide/vue';
import { onMounted } from 'vue';
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
    submitLabel: { type: String, default: 'Simpan Draft' },
});

const emit = defineEmits(['submit']);

// Katalog SEMUA produk aktif (termasuk yang belum berstok). Dimuat sekali.
async function loadProducts() {
    try {
        const res = await fetch(route('openings.picker-products'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        props.form.__catalog = data.products ?? [];
        // Backfill base_unit untuk baris yang sudah ada (mode Edit) supaya
        // preview konversi langsung muncul.
        for (const row of props.form.items) {
            if (row.product_id && !row.base_unit) {
                const prod = props.form.__catalog.find((p) => p.product_id === row.product_id);
                if (prod) row.base_unit = prod.base_unit;
            }
        }
    } catch {
        props.form.__catalog = [];
    }
}

onMounted(() => {
    if (!props.form.__catalog?.length) loadProducts();
});

function catalog() {
    return props.form.__catalog ?? [];
}

function addItem() {
    props.form.items.push({
        product_id: null,
        product_unit_id: null,
        product_name: '',
        product_sku: '',
        base_unit: '',
        batch_code: '',
        expired_date: '',
        qty: 1,
        qty_bonus: 0,
        cost_price: 0,
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
    const prod = catalog().find((p) => p.product_id === id);
    if (prod) {
        row.product_name = prod.name;
        row.product_sku = prod.sku;
        row.base_unit = prod.base_unit;
        // Default ke satuan dasar (biar tetap gampang untuk yang input base).
        row.product_unit_id = prod.base_unit_id ?? (prod.units?.[0]?.product_unit_id ?? null);
    }
}

// Satuan-satuan untuk produk di baris ini.
function unitsForRow(row) {
    if (!row.product_id) return [];
    const prod = catalog().find((p) => p.product_id === row.product_id);
    return prod?.units ?? [];
}

// Faktor konversi satuan yang dipilih di baris ini.
function factorFor(row) {
    const u = unitsForRow(row).find((x) => x.product_unit_id === row.product_unit_id);
    return u ? Number(u.qty_to_base) || 1 : 1;
}

// Preview qty (satuan dipilih) ke satuan dasar (mis. 10 KRT → "= 120 PACK").
function basePreview(row, qty) {
    const factor = factorFor(row);
    if (factor <= 1 || !row.base_unit) return null;
    const base = (Number(qty) || 0) * factor;
    if (base <= 0) return null;
    return `= ${base.toLocaleString('id-ID')} ${row.base_unit}`;
}

const canSubmit = () =>
    props.form.items.length > 0
    && props.form.items.every(
        (r) => r.product_id && r.product_unit_id && (Number(r.qty) > 0 || Number(r.qty_bonus) > 0),
    );
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Header -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3.5">Header</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tanggal *</Label>
                    <Input v-model="form.opening_date" type="date" required class="h-10 rounded-xl" />
                    <p v-if="form.errors.opening_date" class="text-xs text-destructive">{{ form.errors.opening_date }}</p>
                </div>
                <div class="space-y-1 sm:col-span-3">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" class="rounded-xl" placeholder="Keterangan tambahan (opsional)" />
                </div>
            </div>
        </section>

        <!-- Items -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3.5 flex items-center justify-between border-b border-foreground/5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items ({{ form.items.length }})</p>
                <Button type="button" size="sm" variant="outline" class="rounded-full" @click="addItem">
                    <Plus class="size-3.5" /> Tambah Item
                </Button>
            </header>

            <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-4 font-semibold min-w-[220px]">Produk</th>
                            <th class="text-left py-2.5 px-2 font-semibold min-w-[110px]">Satuan</th>
                            <th class="text-right py-2.5 px-2 font-semibold min-w-[130px]">Qty</th>
                            <th class="text-right py-2.5 px-2 font-semibold min-w-[110px]">Bonus</th>
                            <th class="text-left py-2.5 px-2 font-semibold min-w-[120px]">Batch (ops.)</th>
                            <th class="text-left py-2.5 px-2 font-semibold min-w-[130px]">Kadaluarsa (ops.)</th>
                            <th class="text-right py-2.5 px-2 font-semibold">Harga Modal/Satuan</th>
                            <th class="py-2.5 px-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-if="form.items.length === 0">
                            <td colspan="8" class="py-10 text-center text-muted-foreground text-xs">
                                Klik "Tambah Item" untuk mulai. Semua produk aktif bisa dipilih (termasuk yang belum berstok).
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-foreground/2.5 transition-colors align-top">
                            <td class="py-2 px-4">
                                <SearchableSelect
                                    :model-value="row.product_id"
                                    :options="catalog()"
                                    :option-value="(p) => p.product_id"
                                    :option-label="(p) => `${p.name} (${p.sku})`"
                                    :fallback-label="row.product_name ? `${row.product_name}${row.product_sku ? ` (${row.product_sku})` : ''}` : ''"
                                    placeholder="Ketik nama / SKU produk…"
                                    empty-text="Tidak ada produk."
                                    trigger-class="h-9 rounded-lg"
                                    @update:model-value="(v) => onProductChange(idx, v)"
                                >
                                    <template #option="{ option }">
                                        <p class="text-sm font-medium truncate">{{ option.name }}</p>
                                        <p class="text-[11px] text-muted-foreground font-mono">{{ option.sku }} · {{ option.base_unit }}</p>
                                    </template>
                                </SearchableSelect>
                                <p v-if="form.errors[`items.${idx}.product_id`]" class="text-[11px] text-destructive mt-0.5">
                                    {{ form.errors[`items.${idx}.product_id`] }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Select
                                    :model-value="row.product_unit_id ? String(row.product_unit_id) : ''"
                                    :disabled="!row.product_id"
                                    @update:model-value="(v) => (row.product_unit_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger class="h-9 w-full rounded-lg"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in unitsForRow(row)" :key="u.product_unit_id" :value="String(u.product_unit_id)">
                                            {{ u.name }}<span v-if="u.qty_to_base > 1" class="text-muted-foreground"> (×{{ u.qty_to_base }})</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                            <td class="py-2 px-2">
                                <Input
                                    v-model="row.qty"
                                    type="number"
                                    min="0"
                                    class="h-9 rounded-lg text-right font-mono"
                                />
                                <p v-if="basePreview(row, row.qty)" class="text-[11px] text-brand mt-0.5 text-right font-mono">
                                    {{ basePreview(row, row.qty) }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Input
                                    v-model="row.qty_bonus"
                                    type="number"
                                    min="0"
                                    class="h-9 rounded-lg text-right font-mono"
                                    placeholder="0"
                                />
                                <p v-if="Number(row.qty_bonus) > 0 && basePreview(row, row.qty_bonus)" class="text-[11px] text-emerald-600 mt-0.5 text-right font-mono">
                                    {{ basePreview(row, row.qty_bonus) }}
                                </p>
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.batch_code" placeholder="OPENING" class="h-9 rounded-lg" />
                            </td>
                            <td class="py-2 px-2">
                                <Input v-model="row.expired_date" type="date" class="h-9 rounded-lg" />
                            </td>
                            <td class="py-2 px-2">
                                <CurrencyInput v-model="row.cost_price" class="h-9 rounded-lg font-mono" />
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
            <p class="text-[11px] text-muted-foreground px-5 py-3">
                Pilih <strong>satuan</strong> lalu isi qty — mis. 10 KRT otomatis dikonversi ke satuan dasar saat posting.
                <strong>Bonus</strong> = barang gratis (masuk ke stok bonus, harga 0). Batch dikosongkan → otomatis kode <strong>"OPENING"</strong>.
            </p>
        </section>

        <div class="flex justify-end gap-2">
            <slot name="actions" />
            <Button
                type="submit"
                size="default"
                class="rounded-full bg-brand text-white hover:bg-brand-dark"
                :disabled="form.processing || !canSubmit()"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Menyimpan…' : submitLabel }}
            </Button>
        </div>
    </form>
</template>
