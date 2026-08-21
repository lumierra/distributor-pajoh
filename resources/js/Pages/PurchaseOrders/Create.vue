<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShoppingCart } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import PoForm from '@/Components/PurchaseOrders/PoForm.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    suppliers: { type: Array, required: true },
});

const form = useForm({
    supplier_id: null,
    po_date: new Date().toISOString().slice(0, 10),
    eta_date: '',
    payment_term_days: null,
    header_discount_type: null,
    header_discount_value: 0,
    notes: '',
    items: [],
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
        .post(route('purchase-orders.store'));
}
</script>

<template>
    <Head title="Buat Purchase Order" />

    <AppLayout>
        <PageHeader title="Buat Purchase Order" description="Draft PO baru. Setelah approve, status & data terkunci." :icon="ShoppingCart">
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('purchase-orders.index')">
                        <ArrowLeft class="size-4" />
                        Daftar PO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <PoForm :form="form" :suppliers="suppliers" submit-label="Simpan Draft" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('purchase-orders.index')">Batal</Link>
                </Button>
            </template>
        </PoForm>
    </AppLayout>
</template>
