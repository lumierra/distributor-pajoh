<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Ruler } from '@lucide/vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    productId: { type: Number, required: true },
    existingLevels: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const availableLevels = computed(() => {
    const used = new Set(props.existingLevels);
    return ['BSR', 'TGH'].filter((l) => !used.has(l));
});

const form = useForm({
    level: '',
    name: '',
    qty_to_base: 1,
    barcode: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        const lvl = availableLevels.value[0] ?? '';
        const defaults = lvl === 'BSR'
            ? { name: 'Karton', qty_to_base: 40 }
            : lvl === 'TGH'
                ? { name: 'Pak', qty_to_base: 10 }
                : { name: '', qty_to_base: 1 };
        form.defaults({ level: lvl, ...defaults, barcode: '' });
        form.reset();
        form.clearErrors();
    },
);

function onLevelChange(v) {
    form.level = v;
    if (v === 'BSR') {
        form.name = 'Karton';
        form.qty_to_base = 40;
    } else if (v === 'TGH') {
        form.name = 'Pak';
        form.qty_to_base = 10;
    }
}

function submit() {
    form.post(route('products.units.store', props.productId), {
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
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <Ruler class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Tambah Unit
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            BSR &gt; TGH &gt; KCL. Harga di-generate otomatis (0) untuk semua tier.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Level *</Label>
                        <Select :model-value="form.level" @update:model-value="onLevelChange">
                            <SelectTrigger class="h-9">
                                <SelectValue placeholder="Pilih level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="lvl in availableLevels" :key="lvl" :value="lvl">
                                    {{ lvl }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.level" class="text-xs text-destructive">{{ form.errors.level }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama *</Label>
                        <Input v-model="form.name" required class="h-9" placeholder="Karton / Pak" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Isi (qty → KCL) *</Label>
                    <Input
                        v-model="form.qty_to_base"
                        type="number"
                        min="2"
                        required
                        class="h-9 font-mono"
                    />
                    <p v-if="form.errors.qty_to_base" class="text-xs text-destructive">
                        {{ form.errors.qty_to_base }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Barcode (opsional)</Label>
                    <Input v-model="form.barcode" class="h-9 font-mono" placeholder="EAN-13" />
                    <p v-if="form.errors.barcode" class="text-xs text-destructive">{{ form.errors.barcode }}</p>
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
                    :disabled="form.processing || !form.level"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Tambah Unit' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
