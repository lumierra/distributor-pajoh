<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Hash, Loader2, Save } from '@lucide/vue';
import SettingsFields from '@/Components/Settings/SettingsFields.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    groups: { type: Object, required: true },
    sequences: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ update: false }) },
});

function initValues(fields) {
    return Object.fromEntries((fields ?? []).map((f) => [f.key, f.value]));
}

const form = useForm({ numbering: initValues(props.groups.numbering) });

function save() {
    form.post(route('settings.numbering'), { preserveScroll: true });
}

function periodLabel(seq) {
    if (seq.period_month) {
        return `${String(seq.period_month).padStart(2, '0')}/${seq.period_year}`;
    }
    return seq.period_year ? String(seq.period_year) : '—';
}

function fmtDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Penomoran Dokumen" />

    <AppLayout>
        <PageHeader
            title="Penomoran Dokumen"
            description="Format nomor otomatis tiap dokumen (PO, GRN, SO, DO, Faktur, dll) & periode reset."
            :icon="Hash"
        />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Form format -->
            <form class="lg:col-span-2" @submit.prevent="save">
                <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">Format & Reset</p>
                    <p class="text-[12px] text-muted-foreground mb-3.5">
                        Placeholder: <code class="font-mono">{YY}</code> tahun 2 digit, <code class="font-mono">{MM}</code> bulan,
                        <code class="font-mono">{seq:04d}</code> nomor urut 4 digit.
                    </p>
                    <SettingsFields
                        :fields="groups.numbering"
                        v-model="form.numbering"
                        :disabled="!can.update"
                    />
                </section>

                <div v-if="can.update" class="flex justify-end mb-4">
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

            <!-- Sequence berjalan -->
            <aside class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden self-start">
                <header class="px-5 py-3.5 border-b border-foreground/5">
                    <h3 class="text-sm font-semibold">Nomor Urut Berjalan</h3>
                    <p class="text-[12px] text-muted-foreground mt-0.5">Counter terakhir per dokumen & periode.</p>
                </header>
                <ul v-if="sequences.length" class="divide-y divide-foreground/5 max-h-[420px] overflow-y-auto">
                    <li v-for="(seq, i) in sequences" :key="i" class="px-5 py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium font-mono uppercase">{{ seq.doc_type }}</p>
                            <p class="text-[11px] text-muted-foreground">Periode {{ periodLabel(seq) }} · {{ fmtDate(seq.updated_at) }}</p>
                        </div>
                        <span class="text-sm font-semibold font-mono tabular-nums text-brand-dark bg-brand-light/60 rounded-full px-2.5 py-0.5 shrink-0">
                            {{ seq.last_number }}
                        </span>
                    </li>
                </ul>
                <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                    Belum ada nomor yang di-generate.
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
