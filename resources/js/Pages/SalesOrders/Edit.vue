<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ShoppingBag } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import SoForm from '@/Components/SalesOrders/SoForm.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    salesOrder: { type: Object, required: true },
    customers: { type: Array, required: true },
});

const headerDesc = computed(() => {
    switch (props.salesOrder.status) {
        case 'submitted':
        case 'pending_credit_review':
            return 'Edit item & detail. Status tetap seperti sekarang.';
        case 'approved':
            return 'Edit fleksibel — stok akan disesuaikan ulang otomatis.';
        default:
            return 'Edit item & detail SO.';
    }
});

const form = useForm({
    customer_id: props.salesOrder.customer_id,
    so_date: props.salesOrder.so_date ? String(props.salesOrder.so_date).slice(0, 10) : '',
    eta_date: props.salesOrder.eta_date ? String(props.salesOrder.eta_date).slice(0, 10) : '',
    payment_term_days: props.salesOrder.payment_term_days,
    header_discount_type: props.salesOrder.header_discount_type,
    header_discount_value: Number(props.salesOrder.header_discount_value) || 0,
    cashback: Number(props.salesOrder.cashback) || 0,
    notes: props.salesOrder.notes ?? '',
    items: (props.salesOrder.items ?? []).map((i) => ({
        product_id: i.product_id,
        product_unit_id: i.product_unit_id,
        supplier_id: i.supplier_id,
        qty: i.qty,
        discount_type: i.discount_type ?? null,
        discount_value: Number(i.discount_value) || 0,
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
            cashback: Number(data.cashback) || 0,
            notes: data.notes,
            items: data.items.map((r) => ({
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                qty: Number(r.qty) || 0,
                discount_type: r.discount_type || null,
                discount_value: Number(r.discount_value) || 0,
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
        <PageHeader :title="`Edit SO ${salesOrder.so_number}`" :description="headerDesc" :icon="ShoppingBag">
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('sales-orders.show', salesOrder.id)">
                        <ArrowLeft class="size-4" /> Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div v-if="salesOrder.status === 'approved'" class="mb-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200/70 px-4 py-3 flex items-start gap-2.5">
            <AlertTriangle class="size-4 text-amber-600 shrink-0 mt-0.5" />
            <p class="text-xs text-amber-800 leading-relaxed">
                SO ini sudah <strong>approved</strong> — stok sudah terpotong. Menyimpan perubahan akan
                <strong>mengembalikan stok lama lalu memotong ulang</strong> sesuai item baru. Status tetap approved.
                (Bisa karena SO ini belum punya Surat Jalan.)
            </p>
        </div>

        <SoForm :form="form" :customers="customers" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('sales-orders.show', salesOrder.id)">Batal</Link>
                </Button>
            </template>
        </SoForm>
    </AppLayout>
</template>
