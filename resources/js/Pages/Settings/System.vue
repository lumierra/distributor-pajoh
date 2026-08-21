<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Cog, Database, History, Loader2, Save } from '@lucide/vue';
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
    system: initValues(props.groups.system),
    audit: initValues(props.groups.audit),
    closing: initValues(props.groups.closing),
});

function save() {
    form.post(route('settings.system'), { preserveScroll: true });
}

const sections = [
    { group: 'system', title: 'Sistem & Backup', icon: Cog },
    { group: 'audit', title: 'Audit Log', icon: History },
    { group: 'closing', title: 'Tutup Buku', icon: Database },
];
</script>

<template>
    <Head title="Sistem & Backup" />

    <AppLayout>
        <PageHeader
            title="Sistem & Backup"
            description="Konfigurasi dasar aplikasi, jadwal backup, retensi audit log, & notifikasi tutup buku."
            :icon="Cog"
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

            <p class="text-[12px] text-muted-foreground mb-4">
                Beberapa nilai dasar (timezone, mata uang, format tanggal) bersifat baca-saja demi konsistensi data.
            </p>

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
