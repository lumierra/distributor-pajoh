<script setup>
import { Head, router } from '@inertiajs/vue3';
import { History, Search } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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
    rows: { type: Object, required: true },
    users: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    user_id: props.filters.user_id ? String(props.filters.user_id) : ALL,
    channel: props.filters.channel || ALL,
    status:
        props.filters.status === '' || props.filters.status === null || props.filters.status === undefined
            ? ALL
            : String(props.filters.status),
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('login-history.index'),
            {
                q: filters.q,
                user_id: filters.user_id === ALL ? '' : filters.user_id,
                channel: filters.channel === ALL ? '' : filters.channel,
                status: filters.status === ALL ? '' : filters.status,
                from: filters.from,
                to: filters.to,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Login History" />

    <AppLayout>
        <PageHeader
            title="Login History"
            description="Audit trail login web & mobile untuk semua user."
            :icon="History"
        />

        <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden">
            <!-- Filter toolbar -->
            <div class="border-b border-border/70 px-5 py-3.5 grid grid-cols-1 md:grid-cols-6 gap-3">
                <div class="space-y-1.5 md:col-span-2">
                    <Label class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Cari
                    </Label>
                    <div class="relative">
                        <Search
                            class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.q"
                            placeholder="Username, nama…"
                            class="pl-8 h-8"
                        />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        User
                    </Label>
                    <Select v-model="filters.user_id">
                        <SelectTrigger size="sm"><SelectValue placeholder="Semua" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua user</SelectItem>
                            <SelectItem
                                v-for="u in users"
                                :key="u.id"
                                :value="String(u.id)"
                            >
                                {{ u.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Channel
                    </Label>
                    <Select v-model="filters.channel">
                        <SelectTrigger size="sm"><SelectValue placeholder="Semua" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua</SelectItem>
                            <SelectItem value="web">Web</SelectItem>
                            <SelectItem value="mobile">Mobile</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Status
                    </Label>
                    <Select v-model="filters.status">
                        <SelectTrigger size="sm"><SelectValue placeholder="Semua" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua</SelectItem>
                            <SelectItem value="1">Sukses</SelectItem>
                            <SelectItem value="0">Gagal</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Tanggal
                    </Label>
                    <div class="flex gap-1">
                        <Input v-model="filters.from" type="date" class="text-xs h-8" />
                        <Input v-model="filters.to" type="date" class="text-xs h-8" />
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow
                            class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground bg-muted/30"
                        >
                            <TableHead class="pl-5">Waktu</TableHead>
                            <TableHead>User</TableHead>
                            <TableHead>Channel</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>IP</TableHead>
                            <TableHead class="pr-5">Detail</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="rows.data.length === 0">
                            <TableCell colspan="6" class="text-center text-sm text-muted-foreground py-16">
                                Tidak ada baris yang cocok.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="row in rows.data"
                            :key="row.id"
                            :class="[
                                'transition-colors hover:bg-muted/30',
                                !row.is_successful && 'bg-destructive/5',
                            ]"
                        >
                            <TableCell class="pl-5 text-xs whitespace-nowrap">
                                {{ formatDate(row.login_at) }}
                            </TableCell>
                            <TableCell>
                                <template v-if="row.user">
                                    <span class="font-medium">{{ row.user.name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        · {{ row.user.username }}
                                    </span>
                                </template>
                                <span v-else class="italic text-muted-foreground text-xs">
                                    {{ row.username_input || '—' }}
                                </span>
                            </TableCell>
                            <TableCell class="capitalize text-xs">{{ row.channel }}</TableCell>
                            <TableCell>
                                <Badge :variant="row.is_successful ? 'default' : 'destructive'">
                                    {{ row.is_successful ? 'OK' : 'Fail' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-xs font-mono text-muted-foreground">
                                {{ row.ip }}
                            </TableCell>
                            <TableCell class="text-xs pr-5">
                                <span v-if="row.failure_reason" class="text-destructive">
                                    {{ row.failure_reason }}
                                </span>
                                <span v-else-if="row.device_uuid" class="font-mono text-muted-foreground">
                                    {{ row.device_uuid.substring(0, 12) }}…
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="rows.data.length > 0" class="border-t border-border/70 px-5 py-3">
                <Pagination :meta="rows" />
            </div>
        </section>
    </AppLayout>
</template>
