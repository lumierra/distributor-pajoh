<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackagePlus } from '@lucide/vue';
import OpeningForm from '@/Components/Inventory/OpeningForm.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    opening_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [],
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
        .post(route('openings.store'));
}
</script>

<template>
    <Head title="Buat Stok Awal" />

    <AppLayout>
        <PageHeader
            title="Buat Stok Awal"
            description="Saldo awal stok yang sudah ada di gudang, tanpa PO. Setelah posting, stok masuk & terkunci."
            :icon="PackagePlus"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('openings.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Stok Awal
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <OpeningForm :form="form" submit-label="Simpan Draft" @submit="submit">
            <template #actions>
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('openings.index')">Batal</Link>
                </Button>
            </template>
        </OpeningForm>
    </AppLayout>
</template>
