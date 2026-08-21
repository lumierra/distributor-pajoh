<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShoppingCart } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import PoForm from '@/Components/PurchaseOrders/PoForm.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    purchaseOrder: { type: Object, required: true },
    suppliers: { type: Array, required: true },
});

const form = useForm({
    supplier_id: props.purchaseOrder.supplier_id,
    po_date: props.purchaseOrder.po_date ? String(props.purchaseOrder.po_date).slice(0, 10) : '',
    eta_date: props.purchaseOrder.eta_date ? String(props.purchaseOrder.eta_date).slice(0, 10) : '',
    payment_term_days: props.purchaseOrder.payment_term_days,
    header_discount_type: props.purchaseOrder.header_discount_type,
    header_discount_value: Number(props.purchaseOrder.header_discount_value) || 0,
    notes: props.purchaseOrder.notes ?? '',
    items: (props.purchaseOrder.items ?? []).map((i) => ({
        product_id: i.product_id,
        product_unit_id: i.product_unit_id,
        qty_ordered: i.qty_ordered,
        bonus_qty: i.bonus_qty,
        cost_price: Number(i.cost_price),
        discount_z1_pct: Number(i.discount_z1_pct),
        discount_z2_pct: Number(i.discount_z2_pct),
        notes: i.notes ?? '',
    })),
    __supplierProducts: [],
});

function submit() {
    form
        .transform((data) => ({
            supplier_id: data.supplier_id,
            po_date: data.po_date,
            eta_date: data.eta_date || null,
            payment_term_days: data.payment_term_days,
            header_discount_type: data.header_discount_type,
            header_discount_value: data.header_discount_value || 0,
            notes: data.notes,
            items: data.items.map((r) => ({
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                qty_ordered: Number(r.qty_ordered) || 0,
                bonus_qty: Number(r.bonus_qty) || 0,
                cost_price: Number(r.cost_price) || 0,
                discount_z1_pct: Number(r.discount_z1_pct) || 0,
                discount_z2_pct: Number(r.discount_z2_pct) || 0,
                notes: r.notes,
            })),
        }))
        .put(route('purchase-orders.update', props.purchaseOrder.id));
}
</script>

<template>
    <Head :title="`Edit ${purchaseOrder.po_number}`" />

    <AppLayout>
        <PageHeader
            :title="`Edit PO ${purchaseOrder.po_number}`"
            description="Hanya bisa edit selama status Draft."
            :icon="ShoppingCart"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('purchase-orders.show', purchaseOrder.id)">
                        <ArrowLeft class="size-4" />
                        Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <PoForm :form="form" :suppliers="suppliers" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('purchase-orders.show', purchaseOrder.id)">Batal</Link>
                </Button>
            </template>
        </PoForm>
    </AppLayout>
</template>
