<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShoppingBag } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import SoForm from '@/Components/SalesOrders/SoForm.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    customers: { type: Array, required: true },
    salesUsers: { type: Array, default: () => [] },
});

const form = useForm({
    customer_id: null,
    sales_id: null,
    so_date: new Date().toISOString().slice(0, 10),
    eta_date: '',
    payment_term_days: null,
    header_discount_type: null,
    header_discount_value: 0,
    notes: '',
    items: [],
});

function submit() {
    form
        .transform((data) => ({
            customer_id: data.customer_id,
            sales_id: data.sales_id,
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
        .post(route('sales-orders.store'));
}
</script>

<template>
    <Head title="Buat Sales Order" />

    <AppLayout>
        <PageHeader title="Buat Sales Order" description="Draft SO baru. Submit untuk approval admin (atau review credit kalau over limit)." :icon="ShoppingBag">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('sales-orders.index')">
                        <ArrowLeft class="size-4" /> Daftar SO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SoForm :form="form" :customers="customers" :sales-users="salesUsers" submit-label="Simpan Draft" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('sales-orders.index')">Batal</Link>
                </Button>
            </template>
        </SoForm>
    </AppLayout>
</template>
