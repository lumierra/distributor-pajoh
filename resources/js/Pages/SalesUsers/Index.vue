<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Boxes,
    Eye,
    LogIn,
    Plus,
    RotateCcw,
    Search,
    Smartphone,
    UserCheck,
    UserCog,
    UserX,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import SalesFormDialog from '@/Components/Users/SalesFormDialog.vue';
import UserDetailDialog from '@/Components/Users/UserDetailDialog.vue';
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
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['master.user']?.create,
);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    active:
        props.filters.active === '' || props.filters.active === null || props.filters.active === undefined
            ? ALL
            : String(props.filters.active),
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('sales-users.index'),
            {
                q: filters.q,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.active = ALL;
}

const modalOpen = ref(false);
const editingUser = ref(null);
const detailOpen = ref(false);
const detailUser = ref(null);

function openCreate() {
    editingUser.value = null;
    modalOpen.value = true;
}

function openEdit(user) {
    editingUser.value = user;
    modalOpen.value = true;
}

function openDetail(user) {
    detailUser.value = user;
    detailOpen.value = true;
}

function onSaved() {
    editingUser.value = null;
    router.reload({ only: ['users', 'stats'] });
}

function onDetailChanged() {
    router.reload({
        only: ['users', 'stats'],
        onSuccess: () => {
            if (detailUser.value && detailOpen.value) {
                const fresh = props.users.data.find((u) => u.id === detailUser.value.id);
                if (fresh) detailUser.value = fresh;
            }
        },
    });
}

function toggleActive(user) {
    useForm({}).post(route('users.toggle-active', user.id), { preserveScroll: true });
}

function userInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

</script>

<template>
    <Head title="Daftar Sales" />

    <AppLayout>
        <PageHeader
            title="Sales Management"
            description="Kelola akun sales lapangan, Product Group, dan device terdaftar."
            :icon="UserCog"
        >
            <template #actions>
                <Button v-if="canCreate" size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Sales
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total Sales" :value="stats.total ?? 0" tone="brand">
                <template #icon><UserCog class="size-5" /></template>
            </StatCard>
            <StatCard label="Sales Aktif" :value="stats.active ?? 0" tone="brand">
                <template #icon><UserCheck class="size-5" /></template>
            </StatCard>
            <StatCard label="Nonaktif" :value="stats.inactive ?? 0" tone="brand">
                <template #icon><UserX class="size-5" /></template>
            </StatCard>
            <StatCard label="Punya Device" :value="stats.with_device ?? 0" tone="brand-orange">
                <template #icon><Smartphone class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div
                class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama / username / email…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow
                        class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5"
                    >
                        <TableHead class="pl-4">Sales</TableHead>
                        <TableHead>No. HP</TableHead>
                        <TableHead>Product Group</TableHead>
                        <TableHead>Device</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="users.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <UserCog class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada user sales.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="u in users.data"
                        :key="u.id"
                        class="hover:bg-muted/30 transition-colors"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="size-8 rounded-md bg-primary text-primary-foreground flex items-center justify-center text-[11px] font-semibold shadow-sm shrink-0"
                                >
                                    {{ userInitials(u.name) }}
                                </div>
                                <div class="min-w-0">
                                    <button
                                        type="button"
                                        class="text-left font-medium text-foreground truncate leading-tight hover:text-primary transition-colors block"
                                        @click="openDetail(u)"
                                    >
                                        {{ u.name }}
                                    </button>
                                    <p class="text-xs text-muted-foreground truncate leading-tight font-mono">
                                        @{{ u.username }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span v-if="u.phone" class="text-xs font-mono">{{ u.phone }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="(u.product_groups_count ?? 0) > 0"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[11px] font-medium bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200"
                            >
                                <Boxes class="size-3" />
                                {{ u.product_groups_count }} group
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                            >
                                Belum di-assign
                            </span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="(u.active_devices_count ?? 0) > 0"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                            >
                                <Smartphone class="size-3" />
                                {{ u.active_devices_count }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                            >
                                Belum ada
                            </span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="u.is_active"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                            >
                                Aktif
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                            >
                                Nonaktif
                            </span>
                        </TableCell>
                        <TableCell class="py-3 px-4 text-center whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton
                                    :icon="Eye"
                                    label="Detail & aksi cepat"
                                    tone="indigo"
                                    @click="openDetail(u)"
                                />
                                <ActionButton
                                    v-if="u.is_active"
                                    :icon="Ban"
                                    label="Suspend"
                                    tone="amber"
                                    @click="toggleActive(u)"
                                />
                                <ActionButton
                                    v-else
                                    :icon="LogIn"
                                    label="Aktifkan"
                                    tone="emerald"
                                    @click="toggleActive(u)"
                                />
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="users.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="users" />
            </div>
        </section>

        <!-- Create / Edit Modal -->
        <SalesFormDialog v-model:open="modalOpen" :user="editingUser" @saved="onSaved" />

        <!-- Detail Modal (reuse) -->
        <UserDetailDialog
            v-model:open="detailOpen"
            :user="detailUser"
            :can-update="detailUser?.permissions_summary?.canUpdate ?? false"
            :can-reset-password="detailUser?.permissions_summary?.canResetPassword ?? false"
            :can-force-logout="detailUser?.permissions_summary?.canForceLogout ?? false"
            :can-delete="detailUser?.permissions_summary?.canDelete ?? false"
            :can-manage-overrides="detailUser?.permissions_summary?.canManageOverrides ?? false"
            @changed="onDetailChanged"
            @edit-requested="openEdit"
        />
    </AppLayout>
</template>
