<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Package, PackagePlus, Plus, X } from '@lucide/vue';
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
    /** null = create wizard (with units), object = edit basic info only */
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.product);

const form = useForm({
    name: '',
    category_id: null,
    brand: '',
    description: '',
    notes: '',
    is_active: true,
    units: [
        { level: 'KCL', name: 'Pcs', qty_to_base: 1, barcode: '' },
    ],
});

watch(
    () => [props.open, props.product?.id],
    ([open]) => {
        if (!open) return;
        if (props.product) {
            form.defaults({
                name: props.product.name ?? '',
                category_id: props.product.category_id ?? null,
                brand: props.product.brand ?? '',
                description: props.product.description ?? '',
                notes: props.product.notes ?? '',
                is_active: !!props.product.is_active,
                units: [],
            });
        } else {
            form.defaults({
                name: '',
                category_id: props.categories[0]?.id ?? null,
                brand: '',
                description: '',
                notes: '',
                is_active: true,
                units: [{ level: 'KCL', name: 'Pcs', qty_to_base: 1, barcode: '' }],
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function addUnit(level) {
    if (form.units.some((u) => u.level === level)) return;
    const defaults = {
        BSR: { name: 'Karton', qty_to_base: 40 },
        TGH: { name: 'Pak', qty_to_base: 10 },
    };
    const d = defaults[level] ?? { name: '', qty_to_base: 1 };
    form.units = [...form.units, { level, ...d, barcode: '' }];
}

function removeUnit(idx) {
    if (form.units[idx].level === 'KCL') return; // KCL wajib
    form.units = form.units.filter((_, i) => i !== idx);
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

const availableLevels = computed(() => {
    const used = new Set(form.units.map((u) => u.level));
    return ['BSR', 'TGH'].filter((l) => !used.has(l));
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[560px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200"
                    >
                        <component :is="isEdit ? Package : PackagePlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? `Edit Produk — ${product.name}` : 'Tambah Produk' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? `SKU: ${product.sku}. Unit, harga & supplier diatur di halaman detail.`
                                    : 'Produk baru. SKU auto-generate. Tambah unit BSR/TGH bila perlu.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-4 max-h-[65vh] overflow-y-auto" @submit.prevent="submit">
                <!-- Identitas -->
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Identitas
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Nama Produk *</Label>
                            <Input v-model="form.name" required class="h-9" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
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
                            <Label class="text-xs font-medium">Brand</Label>
                            <Input v-model="form.brand" class="h-9" />
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Deskripsi</Label>
                            <Textarea v-model="form.description" rows="2" />
                        </div>
                    </div>
                </div>

                <!-- UoM (create mode only) -->
                <div v-if="!isEdit" class="space-y-3 pt-2 border-t border-border/70">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Unit (UoM)
                        </p>
                        <div class="flex items-center gap-1">
                            <Button
                                v-for="lvl in availableLevels"
                                :key="lvl"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addUnit(lvl)"
                            >
                                <Plus class="size-3" />
                                Tambah {{ lvl }}
                            </Button>
                        </div>
                    </div>

                    <p v-if="form.errors.units" class="text-xs text-destructive">
                        {{ form.errors.units }}
                    </p>

                    <div
                        v-for="(unit, idx) in form.units"
                        :key="`${unit.level}-${idx}`"
                        class="rounded-md ring-1 ring-foreground/10 bg-background p-3 space-y-2"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-primary/10 text-primary"
                            >
                                {{ unit.level }}{{ unit.level === 'KCL' ? ' (base)' : '' }}
                            </span>
                            <button
                                v-if="unit.level !== 'KCL'"
                                type="button"
                                class="text-muted-foreground hover:text-destructive transition-colors"
                                @click="removeUnit(idx)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-xs">Nama</Label>
                                <Input v-model="unit.name" required class="h-8" placeholder="Pcs / Pak / Karton" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Isi (qty → KCL)</Label>
                                <Input
                                    v-model="unit.qty_to_base"
                                    type="number"
                                    min="1"
                                    required
                                    :disabled="unit.level === 'KCL'"
                                    class="h-8 font-mono"
                                />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Barcode (opsional)</Label>
                            <Input v-model="unit.barcode" class="h-8 font-mono" placeholder="EAN-13" />
                        </div>
                    </div>

                    <div
                        class="rounded-md bg-warning-soft ring-1 ring-warning/20 px-3 py-2 text-xs flex items-start gap-2"
                    >
                        <span class="text-base">💡</span>
                        <p class="text-foreground/80 leading-relaxed">
                            KCL = base unit (qty=1). TGH > 1. BSR > TGH. Harga & supplier diatur setelah produk dibuat.
                        </p>
                    </div>
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
                <Button type="button" variant="outline" size="default" @click="close">
                    Batal
                </Button>
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
