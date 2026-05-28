<script setup>
import { useForm } from '@inertiajs/vue3';
import { Factory, Loader2 } from '@lucide/vue';
import { watch } from 'vue';
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
    productId: { type: Number, required: true },
    suppliers: { type: Array, required: true },
    existingSupplierIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({
    supplier_id: null,
    supplier_sku: '',
    moq: '',
    is_primary: false,
    is_active: true,
    notes: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        const firstAvailable = props.suppliers.find(
            (s) => !props.existingSupplierIds.includes(s.id),
        );
        form.defaults({
            supplier_id: firstAvailable?.id ?? null,
            supplier_sku: '',
            moq: '',
            is_primary: props.existingSupplierIds.length === 0,
            is_active: true,
            notes: '',
        });
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    form
        .transform((data) => ({
            ...data,
            moq: data.moq === '' ? null : Number(data.moq),
        }))
        .post(route('products.suppliers.store', props.productId), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved');
            },
        });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[560px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <Factory class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Tautkan Supplier
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Pilih supplier yang menyediakan produk ini. Tepat 1 supplier bisa di-set sebagai primary.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Supplier *</Label>
                    <Select
                        :model-value="form.supplier_id ? String(form.supplier_id) : ''"
                        @update:model-value="(v) => (form.supplier_id = Number(v))"
                    >
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Pilih supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="String(s.id)"
                                :disabled="existingSupplierIds.includes(s.id)"
                            >
                                {{ s.name }} <span class="text-xs text-muted-foreground">({{ s.code }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.supplier_id" class="text-xs text-destructive">
                        {{ form.errors.supplier_id }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">SKU Supplier</Label>
                        <Input v-model="form.supplier_sku" class="h-9 font-mono" placeholder="SKU di sisi supplier" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">MOQ</Label>
                        <Input v-model="form.moq" type="number" min="1" class="h-9 font-mono" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                        <Label class="text-xs font-medium block cursor-pointer">Primary</Label>
                        <Switch v-model="form.is_primary" />
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                        <Label class="text-xs font-medium block cursor-pointer">Aktif</Label>
                        <Switch v-model="form.is_active" />
                    </div>
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing || !form.supplier_id"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Tautkan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
