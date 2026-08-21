<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackageOpen } from '@lucide/vue';
import GrnForm from '@/Components/GoodsReceipts/GrnForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    openPurchaseOrders: { type: Array, required: true },
    suppliers: { type: Array, default: () => [] },
    selectedPo: { type: Object, default: null },
});

const form = useForm({
    // __mode: 'po' (dari PO) | 'direct' (langsung tanpa PO). Kalau datang dgn
    // selectedPo (dari tombol "Buat GRN" di halaman PO), langsung mode PO.
    __mode: props.selectedPo ? 'po' : 'po',
    purchase_order_id: props.selectedPo?.id ?? null,
    supplier_id: null,
    received_date: new Date().toISOString().slice(0, 10),
    supplier_delivery_no: '',
    notes: '',
    discrepancy_notes: '',
    items: [],
    __po: props.selectedPo,
    __supplierProducts: [],
});

function submit() {
    form
        .transform((data) => ({
            purchase_order_id: data.__mode === 'direct' ? null : data.purchase_order_id,
            supplier_id: data.__mode === 'direct' ? data.supplier_id : null,
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
        .post(route('grns.store'));
}
</script>

<template>
    <Head title="Buat GRN" />

    <AppLayout>
        <PageHeader title="Buat Goods Receipt" description="Pilih PO, isi batch & qty diterima. Operator save draft, submit untuk review admin." :icon="PackageOpen">
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('grns.index')">
                        <ArrowLeft class="size-4" />
                        Daftar GRN
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <GrnForm
            :form="form"
            :po-list="openPurchaseOrders"
            :suppliers="suppliers"
            :selected-po="selectedPo"
            mode="create"
            submit-label="Simpan Draft"
            @submit="submit"
        >
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('grns.index')">Batal</Link>
                </Button>
            </template>
        </GrnForm>
    </AppLayout>
</template>
