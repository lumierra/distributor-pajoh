<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Lock, RotateCcw, Search, ShieldAlert } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    action: props.filters.action || ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('settings.sensitive-log'), {
            q: filters.q,
            action: filters.action === ALL ? '' : filters.action,
            from: filters.from,
            to: filters.to,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function reset() {
    filters.q = '';
    filters.action = ALL;
    filters.from = '';
    filters.to = '';
}

const expanded = ref(null);
function toggle(id) {
    expanded.value = expanded.value === id ? null : id;
}

const ACTION_LABEL = {
    deleted: 'Dihapus',
    force_deleted: 'Dihapus Permanen',
    restored: 'Dipulihkan',
    'settings.updated': 'Pengaturan Diubah',
    created: 'Dibuat',
    updated: 'Diubah',
};
function actionLabel(a) {
    return ACTION_LABEL[a] ?? a;
}

function actionTone(a) {
    if (a === 'force_deleted' || a === 'deleted') return { text: 'text-red-700', dot: 'bg-red-500' };
    if (a === 'settings.updated') return { text: 'text-amber-700', dot: 'bg-amber-500' };
    if (a === 'restored') return { text: 'text-emerald-700', dot: 'bg-emerald-600' };
    return { text: 'text-blue-700', dot: 'bg-blue-500' };
}

function modelShort(t) {
    if (!t) return '—';
    return t.split('\\').pop();
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function pretty(obj) {
    if (obj === null || obj === undefined) return '—';
    try {
        return JSON.stringify(obj, null, 2);
    } catch {
        return String(obj);
    }
}
</script>

<template>
    <Head title="Log Sensitif" />

    <AppLayout>
        <PageHeader
            title="Log Sensitif"
            description="Jejak aksi sensitif: penghapusan data, perubahan pengaturan, & entitas keamanan/keuangan."
            :icon="Lock"
        />

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[220px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari label, user, atau aksi…" class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card" />
                </div>
                <Select v-model="filters.action">
                    <SelectTrigger class="w-[180px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Aksi" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Aksi</SelectItem>
                        <SelectItem v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="filters.from" type="date" class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent" />
                <span class="text-xs text-muted-foreground">→</span>
                <Input v-model="filters.to" type="date" class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent" />
                <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                    <RotateCcw class="size-3.5" /> Reset
                </Button>
            </div>

            <div v-if="logs.data.length === 0" class="px-4 py-16 flex flex-col items-center gap-2 text-muted-foreground">
                <ShieldAlert class="size-7 opacity-40" />
                <p class="text-sm">Belum ada aktivitas sensitif tercatat.</p>
            </div>

            <ul v-else class="divide-y divide-foreground/5 border-t border-foreground/5">
                <li v-for="log in logs.data" :key="log.id">
                    <button type="button" class="w-full text-left flex items-center gap-4 px-5 py-3 hover:bg-foreground/2.5 transition-colors" @click="toggle(log.id)">
                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium shrink-0 w-40 justify-center', actionTone(log.action).text]">
                            <span :class="['size-1.5 rounded-full', actionTone(log.action).dot]" />
                            {{ actionLabel(log.action) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium truncate">
                                {{ log.model_label ?? modelShort(log.model_type) }}
                                <span v-if="log.model_type" class="text-[12px] text-muted-foreground font-normal">· {{ modelShort(log.model_type) }}</span>
                            </p>
                            <p class="text-[12px] text-muted-foreground">
                                {{ log.user?.name ?? log.user_name_snapshot ?? 'Sistem' }} · {{ fmt(log.created_at) }}
                                <span v-if="log.ip" class="font-mono"> · {{ log.ip }}</span>
                            </p>
                        </div>
                    </button>

                    <div v-if="expanded === log.id" class="px-5 pb-4 pt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Sebelum</p>
                            <pre class="text-[12px] bg-muted/40 rounded-xl p-3 overflow-x-auto font-mono whitespace-pre-wrap">{{ pretty(log.before) }}</pre>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Sesudah</p>
                            <pre class="text-[12px] bg-muted/40 rounded-xl p-3 overflow-x-auto font-mono whitespace-pre-wrap">{{ pretty(log.after ?? log.context) }}</pre>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-if="logs.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="logs" />
            </div>
        </section>
    </AppLayout>
</template>
