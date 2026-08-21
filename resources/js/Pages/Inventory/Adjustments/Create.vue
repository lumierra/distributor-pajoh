<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, SlidersHorizontal } from '@lucide/vue';
import AdjustmentForm from '@/Components/Inventory/AdjustmentForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    adjustment_date: new Date().toISOString().slice(0, 10),
    reason_category: 'damaged',
    notes: '',
    items: [],
    __catalog: [],
});

function submit() {
    form
        .transform((data) => ({
            adjustment_date: data.adjustment_date,
            reason_category: data.reason_category,
            notes: data.notes,
            items: data.items.map((r) => ({
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                batch_id: r.batch_id,
                direction: r.direction,
                qty: Number(r.qty) || 0,
                cost_price: Number(r.cost_price) || 0,
                notes: r.notes,
            })),
        }))
        .post(route('adjustments.store'));
}
</script>

<template>
    <Head title="Buat Penyesuaian Stok" />

    <AppLayout>
        <PageHeader
            title="Buat Penyesuaian Stok"
            description="Draft koreksi stok. Setelah posting, stok berubah & terkunci."
            :icon="SlidersHorizontal"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('adjustments.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Adjustment
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <AdjustmentForm :form="form" submit-label="Simpan Draft" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('adjustments.index')">Batal</Link>
                </Button>
            </template>
        </AdjustmentForm>
    </AppLayout>
</template>
