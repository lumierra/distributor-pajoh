<script setup>
import { useForm } from '@inertiajs/vue3';
import { Check, Loader2, Package, PackagePlus, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = create wizard, object = edit basic info only */
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    /** Master satuan dari /units */
    unitsMaster: { type: Array, default: () => [] },
    /** Daftar supplier aktif untuk picker tagging */
    suppliers: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.product);

const form = useForm({
    name: '',
    category_id: null,
    description: '',
    notes: '',
    is_active: true,
    units: [
        { unit_id: null, qty_to_base: 1, barcode: '' },
    ],
    supplier_ids: [],
});

watch(
    () => [props.open, props.product?.id],
    ([open]) => {
        if (!open) return;
        if (props.product) {
            form.defaults({
                name: props.product.name ?? '',
                category_id: props.product.category_id ?? null,
                description: props.product.description ?? '',
                notes: props.product.notes ?? '',
                is_active: !!props.product.is_active,
                units: [],
                supplier_ids: [],
            });
        } else {
            form.defaults({
                name: '',
                category_id: props.categories[0]?.id ?? null,
                description: '',
                notes: '',
                is_active: true,
                units: [{ unit_id: null, qty_to_base: 1, barcode: '' }],
                supplier_ids: [],
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function addUnit() {
    form.units = [...form.units, { unit_id: null, qty_to_base: 1, barcode: '' }];
}

function removeUnit(idx) {
    if (form.units.length <= 1) return; // minimal 1 unit
    form.units = form.units.filter((_, i) => i !== idx);
}

/** Toggle supplier di list multi-select */
function toggleSupplier(id) {
    const next = new Set(form.supplier_ids);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    form.supplier_ids = Array.from(next);
}

function close() {
    emit('update:open', false);
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('products.update', props.product.id), opts);
    } else {
        form.post(route('products.store'), opts);
    }
}

const baseUnitCount = computed(
    () => form.units.filter((u) => Number(u.qty_to_base) === 1).length,
);

/** Untuk tiap row, list unit master yg masih bisa dipilih (exclude yg sudah dipakai row lain) */
function availableUnitsFor(rowIdx) {
    const usedIds = new Set(
        form.units
            .map((u, i) => (i === rowIdx ? null : u.unit_id))
            .filter((v) => v !== null),
    );
    return props.unitsMaster.filter((u) => !usedIds.has(u.id));
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[600px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <component :is="isEdit ? Package : PackagePlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? `Edit Produk — ${product.name}` : 'Tambah Produk' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? `SKU: ${product.sku}. Satuan, harga & supplier diatur di halaman detail.`
                                    : 'Produk baru. SKU auto-generate. Tambah satuan + pilih supplier penyedia.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto" @submit.prevent="submit">
                <!-- Identitas -->
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Identitas
                    </p>
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Nama Produk *</Label>
                            <Input v-model="form.name" required class="h-9" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kategori</Label>
                            <Select v-model="form.category_id">
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="Pilih kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in categories" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Deskripsi</Label>
                            <Textarea v-model="form.description" rows="2" />
                        </div>
                    </div>
                </div>

                <!-- Satuan (create only) -->
                <div v-if="!isEdit" class="space-y-3 pt-2 border-t border-border/70">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Satuan
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="form.units.length >= unitsMaster.length"
                            @click="addUnit"
                        >
                            <Plus class="size-3" />
                            Tambah Satuan
                        </Button>
                    </div>

                    <p v-if="form.errors.units" class="text-xs text-destructive">{{ form.errors.units }}</p>

                    <div
                        v-for="(u, idx) in form.units"
                        :key="idx"
                        class="rounded-md ring-1 ring-foreground/10 bg-background p-3 space-y-2"
                    >
                        <div class="grid grid-cols-[1fr_120px_auto] gap-2 items-end">
                            <div class="space-y-1">
                                <Label class="text-xs">Satuan</Label>
                                <Select
                                    :model-value="u.unit_id ? String(u.unit_id) : ''"
                                    @update:model-value="(v) => (u.unit_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger class="h-9">
                                        <SelectValue placeholder="Pilih satuan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="m in availableUnitsFor(idx)"
                                            :key="m.id"
                                            :value="String(m.id)"
                                        >
                                            {{ m.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Qty ke base</Label>
                                <Input
                                    v-model="u.qty_to_base"
                                    type="number"
                                    min="1"
                                    required
                                    class="h-9 font-mono text-right"
                                />
                            </div>
                            <button
                                type="button"
                                class="size-9 rounded-md hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors"
                                :disabled="form.units.length <= 1"
                                @click="removeUnit(idx)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Barcode (opsional)</Label>
                            <Input v-model="u.barcode" class="h-8 font-mono" placeholder="EAN-13" />
                        </div>
                    </div>

                    <div class="rounded-md bg-warning-soft ring-1 ring-warning/20 px-3 py-2 text-xs flex items-start gap-2">
                        <span class="text-base">💡</span>
                        <p class="text-foreground/80 leading-relaxed">
                            Satuan dgn <strong>Qty ke base = 1</strong> jadi base unit (sumber stok).
                            Wajib tepat 1 base. Mis: PCS qty=1, PAK qty=10, KARDUS qty=240.
                            <span v-if="baseUnitCount === 0" class="text-destructive block mt-1">
                                ⚠ Belum ada base unit (qty=1).
                            </span>
                            <span v-if="baseUnitCount > 1" class="text-destructive block mt-1">
                                ⚠ Hanya boleh 1 base unit (qty=1).
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Supplier picker (create only) -->
                <div v-if="!isEdit" class="space-y-2 pt-2 border-t border-border/70">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Supplier Penyedia *
                        </p>
                        <span class="text-[11px] text-muted-foreground">
                            Terpilih: <strong>{{ form.supplier_ids.length }}</strong>
                        </span>
                    </div>
                    <p v-if="form.errors.supplier_ids" class="text-xs text-destructive">
                        {{ form.errors.supplier_ids }}
                    </p>
                    <div class="max-h-[180px] overflow-y-auto rounded-md ring-1 ring-foreground/10 divide-y divide-border/40">
                        <button
                            v-for="s in suppliers"
                            :key="s.id"
                            type="button"
                            class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-muted/30 text-left"
                            @click="toggleSupplier(s.id)"
                        >
                            <div
                                :class="[
                                    'size-5 rounded border flex items-center justify-center shrink-0 transition-colors',
                                    form.supplier_ids.includes(s.id)
                                        ? 'bg-emerald-600 border-emerald-600 text-white'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <Check v-if="form.supplier_ids.includes(s.id)" class="size-3.5" />
                            </div>
                            <div class="flex-1 min-w-0 text-sm">
                                <p class="font-medium truncate">{{ s.name }}</p>
                                <p class="text-[10px] text-muted-foreground font-mono">{{ s.code }}</p>
                            </div>
                        </button>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Wajib minimal 1 supplier. Supplier pertama jadi <strong>primary</strong>. Atur harga per supplier setelah produk dibuat.
                    </p>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer">Status aktif</Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Produk nonaktif tidak muncul di dropdown SO/PO.
                        </p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">Batal</Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Produk' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
