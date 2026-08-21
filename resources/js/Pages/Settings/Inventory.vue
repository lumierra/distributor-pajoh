<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Boxes, Loader2, Save, Truck, Users } from '@lucide/vue';
import SettingsFields from '@/Components/Settings/SettingsFields.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    groups: { type: Object, required: true },
    can: { type: Object, default: () => ({ update: false }) },
});

function initValues(fields) {
    return Object.fromEntries((fields ?? []).map((f) => [f.key, f.value]));
}

const form = useForm({
    inventory: initValues(props.groups.inventory),
    customer: initValues(props.groups.customer),
    vehicle: initValues(props.groups.vehicle),
});

function save() {
    form.post(route('settings.inventory'), { preserveScroll: true });
}

const sections = [
    { group: 'inventory', title: 'Inventory', icon: Boxes },
    { group: 'customer', title: 'Customer', icon: Users },
    { group: 'vehicle', title: 'Kendaraan', icon: Truck },
];
</script>

<template>
    <Head title="Inventory & Customer" />

    <AppLayout>
        <PageHeader
            title="Inventory & Customer"
            description="Default stok (negatif, reorder, expired), harga kredit customer, & aturan kendaraan."
            :icon="Boxes"
        />

        <form class="max-w-3xl" @submit.prevent="save">
            <section
                v-for="s in sections"
                :key="s.group"
                class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4"
            >
                <div class="flex items-center gap-2 mb-3.5">
                    <span class="size-7 rounded-full bg-brand-light/70 text-brand flex items-center justify-center">
                        <component :is="s.icon" class="size-4" />
                    </span>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{{ s.title }}</p>
                </div>
                <SettingsFields
                    :fields="groups[s.group]"
                    v-model="form[s.group]"
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
