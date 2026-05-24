<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Truck } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    deliveryOrder: { type: Object, required: true },
    so: { type: Object, default: null },
});

const form = useForm({
    do_date: props.deliveryOrder.do_date ? String(props.deliveryOrder.do_date).slice(0, 10) : '',
    expected_delivery_date: props.deliveryOrder.expected_delivery_date
        ? String(props.deliveryOrder.expected_delivery_date).slice(0, 10)
        : '',
    notes: props.deliveryOrder.notes ?? '',
    items: (props.deliveryOrder.items ?? []).map((i) => ({
        so_item_id: i.so_item_id,
        qty_planned: i.qty_planned,
        product_name: i.product_name_snapshot,
        product_sku: i.product_sku_snapshot,
        unit_name: i.product_unit_name_snapshot,
    })),
});

function submit() {
    form
        .transform((data) => ({
            do_date: data.do_date,
            expected_delivery_date: data.expected_delivery_date || null,
            notes: data.notes,
            items: data.items
                .filter((r) => Number(r.qty_planned) > 0)
                .map((r) => ({
                    so_item_id: r.so_item_id,
                    qty_planned: Number(r.qty_planned),
                })),
        }))
        .put(route('delivery-orders.update', props.deliveryOrder.id));
}
</script>

<template>
    <Head :title="`Edit ${deliveryOrder.do_number}`" />

    <AppLayout>
        <PageHeader :title="`Edit DO ${deliveryOrder.do_number}`" description="Hanya draft yang bisa diedit." :icon="Truck">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('delivery-orders.show', deliveryOrder.id)">
                        <ArrowLeft class="size-4" /> Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tgl DO *</Label>
                        <Input v-model="form.do_date" type="date" required class="h-9" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Estimasi Tiba</Label>
                        <Input v-model="form.expected_delivery_date" type="date" class="h-9" />
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
                <header class="border-b border-border/70 px-5 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items</p>
                </header>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2 px-5">Produk</th>
                            <th class="text-left py-2 px-3">Unit</th>
                            <th class="text-right py-2 px-5 w-[15%]">Qty Plan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-muted/20">
                            <td class="py-2 px-5">
                                <p class="font-medium text-xs">{{ row.product_name }}</p>
                                <p class="text-[10px] text-muted-foreground font-mono">{{ row.product_sku }}</p>
                            </td>
                            <td class="py-2 px-3 text-xs">{{ row.unit_name }}</td>
                            <td class="py-2 px-5">
                                <Input v-model="row.qty_planned" type="number" min="0" class="h-8 text-right font-mono" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('delivery-orders.show', deliveryOrder.id)">Batal</Link>
                </Button>
                <Button type="submit" variant="secondary" size="default" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    Simpan
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
