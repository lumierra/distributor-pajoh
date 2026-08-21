<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2, Save, Smartphone } from '@lucide/vue';
import SettingsFields from '@/Components/Settings/SettingsFields.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    groups: { type: Object, required: true },
    can: { type: Object, default: () => ({ update: false }) },
});

// Bangun objek nilai awal { key: value } dari definisi field group `sales`.
function initValues(fields) {
    return Object.fromEntries((fields ?? []).map((f) => [f.key, f.value]));
}

const form = useForm({ sales: initValues(props.groups.sales) });

function save() {
    form.post(route('settings.sales'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Sales & Mobile" />

    <AppLayout>
        <PageHeader
            title="Sales & Mobile"
            description="Aturan aplikasi mobile sales: geofence kunjungan, binding perangkat, deteksi fake GPS."
            :icon="Smartphone"
        />

        <form class="max-w-3xl" @submit.prevent="save">
            <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3.5">Geofence, Perangkat & Fake GPS</p>
                <SettingsFields
                    :fields="groups.sales"
                    v-model="form.sales"
                    :disabled="!can.update"
                />
            </section>

            <div v-if="can.update" class="flex justify-end">
                <Button
                    type="submit"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
