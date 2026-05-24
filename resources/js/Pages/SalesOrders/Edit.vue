<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShoppingBag } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import SoForm from '@/Components/SalesOrders/SoForm.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    salesOrder: { type: Object, required: true },
    customers: { type: Array, required: true },
});

const form = useForm({
    customer_id: props.salesOrder.customer_id,
    so_date: props.salesOrder.so_date ? String(props.salesOrder.so_date).slice(0, 10) : '',
    eta_date: props.salesOrder.eta_date ? String(props.salesOrder.eta_date).slice(0, 10) : '',
    payment_term_days: props.salesOrder.payment_term_days,
    header_discount_type: props.salesOrder.header_discount_type,
    header_discount_value: Number(props.salesOrder.header_discount_value) || 0,
    notes: props.salesOrder.notes ?? '',
    items: (props.salesOrder.items ?? []).map((i) => ({
        product_id: i.product_id,
        product_unit_id: i.product_unit_id,
        qty: i.qty,
        discount_z1_pct: Number(i.discount_z1_pct),
        discount_z2_pct: Number(i.discount_z2_pct),
        is_bonus: !!i.is_bonus,
        notes: i.notes ?? '',
        _unit_price: Number(i.unit_price),
        _product_name: i.product_name_snapshot,
    })),
});

function submit() {
    form
        .transform((data) => ({
            customer_id: data.customer_id,
            so_date: data.so_date,
            eta_date: data.eta_date || null,
            payment_term_days: data.payment_term_days,
            header_discount_type: data.header_discount_type,
            header_discount_value: data.header_discount_value || 0,
            notes: data.notes,
            items: data.items.map((r) => ({
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                qty: Number(r.qty) || 0,
                discount_z1_pct: Number(r.discount_z1_pct) || 0,
                discount_z2_pct: Number(r.discount_z2_pct) || 0,
                is_bonus: !!r.is_bonus,
                notes: r.notes,
            })),
        }))
        .put(route('sales-orders.update', props.salesOrder.id));
}
</script>

<template>
    <Head :title="`Edit ${salesOrder.so_number}`" />

    <AppLayout>
        <PageHeader :title="`Edit SO ${salesOrder.so_number}`" description="Hanya bisa edit selama status Draft." :icon="ShoppingBag">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('sales-orders.show', salesOrder.id)">
                        <ArrowLeft class="size-4" /> Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SoForm :form="form" :customers="customers" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('sales-orders.show', salesOrder.id)">Batal</Link>
                </Button>
            </template>
        </SoForm>
    </AppLayout>
</template>
