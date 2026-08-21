<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackageOpen } from '@lucide/vue';
import GrnForm from '@/Components/GoodsReceipts/GrnForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    grn: { type: Object, required: true },
    po: { type: Object, default: null },
});

const form = useForm({
    __mode: props.grn.purchase_order_id ? 'po' : 'direct',
    __supplierName: props.grn.supplier?.name ?? '—',
    purchase_order_id: props.grn.purchase_order_id,
    supplier_id: props.grn.supplier_id,
    received_date: props.grn.received_date ? String(props.grn.received_date).slice(0, 10) : '',
    supplier_delivery_no: props.grn.supplier_delivery_no ?? '',
    notes: props.grn.notes ?? '',
    discrepancy_notes: props.grn.discrepancy_notes ?? '',
    items: (props.grn.items ?? []).map((i) => {
        const poItem = (props.po?.items ?? []).find((x) => x.id === i.po_item_id);

        return {
            po_item_id: i.po_item_id,
            product_id: i.product_id,
            product_unit_id: i.product_unit_id,
            product_name: i.product_name_snapshot,
            product_sku: i.product_sku_snapshot,
            unit_name: i.product_unit_name_snapshot,
            qty_ordered: poItem?.qty_ordered ?? 0,
            qty_received_already: poItem?.qty_received ?? 0,
            qty_remaining: poItem?.qty_remaining ?? 0,
            bonus_qty: poItem?.bonus_qty ?? 0,
            bonus_qty_received_already: poItem?.bonus_qty_received ?? 0,
            batch_code: i.batch_code,
            production_date: i.production_date ? String(i.production_date).slice(0, 10) : '',
            expired_date: i.expired_date ? String(i.expired_date).slice(0, 10) : '',
            qty_reguler: i.qty_reguler,
            qty_delivery_note: i.qty_delivery_note ?? i.qty_reguler,
            qty_bonus: i.qty_bonus,
            qty_damaged: i.qty_damaged,
            cost_price: Number(i.cost_price),
            condition: i.condition,
            notes: i.notes ?? '',
        };
    }),
    __po: props.po,
    __supplierProducts: [],
});

function submit() {
    form
        .transform((data) => ({
            received_date: data.received_date,
            supplier_delivery_no: data.supplier_delivery_no,
            notes: data.notes,
            discrepancy_notes: data.discrepancy_notes,
            items: data.items.map((r) => ({
                po_item_id: r.po_item_id ?? null,
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                batch_code: r.batch_code,
                production_date: r.production_date || null,
                expired_date: r.expired_date || null,
                qty_reguler: Number(r.qty_reguler) || 0,
                qty_delivery_note: Number(r.qty_delivery_note) || 0,
                qty_bonus: Number(r.qty_bonus) || 0,
                qty_damaged: Number(r.qty_damaged) || 0,
                cost_price: Number(r.cost_price) || 0,
                condition: r.condition,
                notes: r.notes,
            })),
        }))
        .put(route('grns.update', props.grn.id));
}
</script>

<template>
    <Head :title="`Edit ${grn.grn_number}`" />

    <AppLayout>
        <PageHeader :title="`Edit GRN ${grn.grn_number}`" description="Hanya bisa edit selama status Draft atau Rejected." :icon="PackageOpen">
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('grns.show', grn.id)">
                        <ArrowLeft class="size-4" />
                        Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <GrnForm :form="form" mode="edit" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('grns.show', grn.id)">Batal</Link>
                </Button>
            </template>
        </GrnForm>
    </AppLayout>
</template>
