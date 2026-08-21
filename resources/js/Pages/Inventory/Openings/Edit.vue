<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackagePlus } from '@lucide/vue';
import OpeningForm from '@/Components/Inventory/OpeningForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    opening: { type: Object, required: true },
});

const form = useForm({
    opening_date: props.opening.opening_date ? String(props.opening.opening_date).slice(0, 10) : '',
    notes: props.opening.notes ?? '',
    items: (props.opening.items ?? []).map((i) => ({
        product_id: i.product_id,
        product_unit_id: i.product_unit_id,
        product_name: i.product_name_snapshot,
        product_sku: i.product_sku_snapshot,
        base_unit: '',
        batch_code: i.batch_code === 'OPENING' ? '' : i.batch_code,
        expired_date: i.expired_date ? String(i.expired_date).slice(0, 10) : '',
        qty: i.qty,
        qty_bonus: i.qty_bonus ?? 0,
        cost_price: Number(i.cost_price),
        notes: i.notes ?? '',
    })),
    __catalog: [],
});

function submit() {
    form
        .transform((data) => ({
            opening_date: data.opening_date,
            notes: data.notes,
            items: data.items.map((r) => ({
                product_id: r.product_id,
                product_unit_id: r.product_unit_id,
                batch_code: r.batch_code || null,
                expired_date: r.expired_date || null,
                qty: Number(r.qty) || 0,
                qty_bonus: Number(r.qty_bonus) || 0,
                cost_price: Number(r.cost_price) || 0,
                notes: r.notes,
            })),
        }))
        .put(route('openings.update', props.opening.id));
}
</script>

<template>
    <Head :title="`Edit ${opening.opening_number}`" />

    <AppLayout>
        <PageHeader
            :title="`Edit ${opening.opening_number}`"
            description="Hanya bisa edit selama status Draft."
            :icon="PackagePlus"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('openings.show', opening.id)">
                        <ArrowLeft class="size-4" />
                        Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <OpeningForm :form="form" submit-label="Simpan Perubahan" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('openings.show', opening.id)">Batal</Link>
                </Button>
            </template>
        </OpeningForm>
    </AppLayout>
</template>
