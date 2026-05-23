<script setup>
import { useForm } from '@inertiajs/vue3';
import { DollarSign, Loader2 } from '@lucide/vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    product: { type: Object, required: true },
    tiers: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({ prices: [] });

function buildPriceRows() {
    const map = new Map();
    for (const p of props.product.prices ?? []) {
        map.set(`${p.product_unit_id}|${p.price_tier_id}`, Number(p.price));
    }
    const rows = [];
    for (const u of props.product.units ?? []) {
        for (const t of props.tiers) {
            rows.push({
                product_unit_id: u.id,
                price_tier_id: t.id,
                unit_name: u.name,
                unit_level: u.level,
                tier_name: t.name,
                price: map.get(`${u.id}|${t.id}`) ?? 0,
            });
        }
    }
    return rows;
}

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({ prices: buildPriceRows() });
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    const payload = {
        prices: form.prices.map((r) => ({
            product_unit_id: r.product_unit_id,
            price_tier_id: r.price_tier_id,
            price: Number(r.price) || 0,
        })),
    };
    useForm(payload).put(route('products.prices.update', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('saved');
        },
    });
}

function formatRupiah(v) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
    }).format(Number(v) || 0);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <DollarSign class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Atur Harga — {{ product.name }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Matrix harga per Unit × Tier. Perubahan tercatat di history.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="px-5 py-4 max-h-[60vh] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2">Unit</th>
                            <th class="text-left py-2">Tier</th>
                            <th class="text-right py-2 pr-1">Harga (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="(row, idx) in form.prices" :key="idx" class="hover:bg-muted/20">
                            <td class="py-2">
                                <span class="font-medium">{{ row.unit_name }}</span>
                                <span class="text-[10px] text-muted-foreground ml-1">({{ row.unit_level }})</span>
                            </td>
                            <td class="py-2 text-xs">{{ row.tier_name }}</td>
                            <td class="py-1 text-right">
                                <Input
                                    v-model="row.price"
                                    type="number"
                                    min="0"
                                    step="100"
                                    class="h-8 text-right font-mono w-40 ml-auto"
                                />
                            </td>
                        </tr>
                        <tr v-if="form.prices.length === 0">
                            <td colspan="3" class="py-8 text-center text-muted-foreground text-xs">
                                Belum ada unit. Tambah unit dulu di tab UoM.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="form.errors.prices" class="text-xs text-destructive mt-2">{{ form.errors.prices }}</p>
            </div>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing || form.prices.length === 0"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Harga' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
