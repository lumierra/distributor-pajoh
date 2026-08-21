<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, SlidersHorizontal } from '@lucide/vue';
import AdjustmentForm from '@/Components/Inventory/AdjustmentForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    adjustment: { type: Object, required: true },
});

const form = useForm({
    adjustment_date: props.adjustment.adjustment_date ? String(props.adjustment.adjustment_date).slice(0, 10) : '',
    reason_category: props.adjustment.reason_category,
    notes: props.adjustment.notes ?? '',
    items: (props.adjustment.items ?? []).map((i) => ({
        product_id: i.product_id,
        product_unit_id: i.product_unit_id,
        batch_id: i.batch_id,
        product_name: i.product_name_snapshot,
        product_sku: i.product_sku_snapshot,
        batch_code: i.batch_code_snapshot,
        base_unit: '',
        direction: i.direction,
        qty: i.qty,
        cost_price: Number(i.cost_price),
        notes: i.notes ?? '',
        _system_qty: i.system_qty_snapshot,
        _units: [],
    })),
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
        .put(route('adjustments.update', props.adjustment.id));
}
</script>

<template>
    <Head :title="`Edit ${adjustment.adjustment_number}`" />

    <AppLayout>
        <PageHeader
            :title="`Edit ${adjustment.adjustment_number}`"
            description="Hanya bisa edit selama status Draft."
            :icon="SlidersHorizontal"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('adjustments.show', adjustment.id)">
                        <ArrowLeft class="size-4" />
                        Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <AdjustmentForm :form="form" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('adjustments.show', adjustment.id)">Batal</Link>
                </Button>
            </template>
        </AdjustmentForm>
    </AppLayout>
</template>
