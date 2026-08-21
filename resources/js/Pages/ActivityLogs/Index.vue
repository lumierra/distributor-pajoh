<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Eye } from '@lucide/vue';
import { reactive, watch } from 'vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ? String(props.filters.user_id) : ALL,
    action: props.filters.action || ALL,
    model_type: props.filters.model_type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('activity-logs.index'), {
            q: filters.q,
            user_id: filters.user_id === ALL ? '' : filters.user_id,
            action: filters.action === ALL ? '' : filters.action,
            model_type: filters.model_type,
            from: filters.from,
            to: filters.to,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Activity Log" />

    <AppLayout>
        <PageHeader title="Activity Log" description="Audit trail aktivitas user di seluruh sistem." :icon="ClipboardList" />

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.q" placeholder="Cari user/label..." class="w-[200px] h-9" />
                <Select v-model="filters.user_id">
                    <SelectTrigger class="w-[180px] h-9"><SelectValue placeholder="User" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua User</SelectItem>
                        <SelectItem v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.action">
                    <SelectTrigger class="w-[160px] h-9"><SelectValue placeholder="Action" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Action</SelectItem>
                        <SelectItem v-for="a in actions" :key="a" :value="a">{{ a }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="filters.model_type" placeholder="Model..." class="w-[140px] h-9" />
                <Input v-model="filters.from" type="date" class="w-[140px] h-9" />
                <Input v-model="filters.to" type="date" class="w-[140px] h-9" />
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Tgl</TableHead>
                        <TableHead>User</TableHead>
                        <TableHead>Action</TableHead>
                        <TableHead>Model</TableHead>
                        <TableHead>Label</TableHead>
                        <TableHead class="text-right pr-4">Detail</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="logs.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16 text-muted-foreground text-sm">Belum ada activity log.</TableCell>
                    </TableRow>
                    <TableRow v-for="log in logs.data" :key="log.id">
                        <TableCell class="pl-4 text-xs">{{ fmt(log.created_at) }}</TableCell>
                        <TableCell class="text-xs">{{ log.user?.name ?? log.user_name_snapshot ?? '—' }}</TableCell>
                        <TableCell class="text-xs font-mono">{{ log.action }}</TableCell>
                        <TableCell class="text-[12px] text-muted-foreground">{{ log.model_type?.split('\\').pop() ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ log.model_label ?? '—' }}</TableCell>
                        <TableCell class="text-right pr-4">
                            <Button as-child size="sm" variant="ghost" class="h-7 px-2">
                                <Link :href="route('activity-logs.show', log.id)">
                                    <Eye class="size-3.5" />
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="logs.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="logs" />
            </div>
        </section>
    </AppLayout>
</template>
