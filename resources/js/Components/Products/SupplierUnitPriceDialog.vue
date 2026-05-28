<script setup>
import { useForm } from '@inertiajs/vue3';
import { DollarSign, Loader2 } from '@lucide/vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    productId: { type: Number, required: true },
    productUnits: { type: Array, required: true }, // dari product.units
    suppliers: { type: Array, required: true }, // semua supplier aktif
    /** existing row supplier_product_unit kalau edit, null kalau create */
    row: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.row);

const form = useForm({
    supplier_id: null,
    product_unit_id: null,
    cost_price: 0,
    sell_price: 0,
    is_active: true,
});

watch(
    () => [props.open, props.row?.id],
    ([open]) => {
        if (!open) return;
        if (props.row) {
            form.defaults({
                supplier_id: props.row.supplier_id,
                product_unit_id: props.row.product_unit_id,
                cost_price: Number(props.row.cost_price ?? 0),
                sell_price: Number(props.row.sell_price ?? 0),
                is_active: !!props.row.is_active,
            });
        } else {
            form.defaults({
                supplier_id: props.suppliers[0]?.id ?? null,
                product_unit_id: props.productUnits[0]?.id ?? null,
                cost_price: 0,
                sell_price: 0,
                is_active: true,
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('supplier-product-units.update', props.row.id), opts);
    } else {
        form.post(route('products.supplier-units.store', props.productId), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <DollarSign class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? 'Edit Harga Supplier' : 'Tambah Harga Supplier' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Set modal & jual untuk kombinasi supplier × satuan.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Supplier *</Label>
                    <Select
                        :model-value="form.supplier_id ? String(form.supplier_id) : ''"
                        :disabled="isEdit"
                        @update:model-value="(v) => (form.supplier_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger class="h-9"><SelectValue placeholder="Pilih supplier" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">
                                {{ s.name }} <span class="text-[10px] text-muted-foreground ml-1">{{ s.code }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.supplier_id" class="text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">Satuan *</Label>
                    <Select
                        :model-value="form.product_unit_id ? String(form.product_unit_id) : ''"
                        :disabled="isEdit"
                        @update:model-value="(v) => (form.product_unit_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger class="h-9"><SelectValue placeholder="Pilih satuan" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="u in productUnits" :key="u.id" :value="String(u.id)">
                                {{ u.name }} <span class="text-[10px] text-muted-foreground ml-1">({{ u.level }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.product_unit_id" class="text-xs text-destructive">{{ form.errors.product_unit_id }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Harga Modal (Rp)</Label>
                        <Input v-model="form.cost_price" type="number" min="0" step="100" class="h-9 font-mono" />
                        <p v-if="form.errors.cost_price" class="text-xs text-destructive">{{ form.errors.cost_price }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Harga Jual (Rp)</Label>
                        <Input v-model="form.sell_price" type="number" min="0" step="100" class="h-9 font-mono" />
                        <p v-if="form.errors.sell_price" class="text-xs text-destructive">{{ form.errors.sell_price }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                    <Label class="text-xs font-medium block cursor-pointer">Aktif</Label>
                    <Switch v-model="form.is_active" />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">Batal</Button>
                <Button type="button" variant="secondary" size="default" :disabled="form.processing" @click="submit">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
