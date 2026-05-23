<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    KeyRound,
    LogIn,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Shield,
    ShieldCheck,
    Trash2,
    UserCheck,
    Users as UsersIcon,
    UserX,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import UserFormDialog from '@/Components/Users/UserFormDialog.vue';
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
    roles: { type: Array, required: true },
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
    role: props.filters.role || ALL,
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
            route('users.index'),
            {
                q: filters.q,
                role: filters.role === ALL ? '' : filters.role,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.role = ALL;
    filters.active = ALL;
}

// ── Modal state ──
const modalOpen = ref(false);
const editingUser = ref(null);

function openCreate() {
    editingUser.value = null;
    modalOpen.value = true;
}

function openEdit(user) {
    editingUser.value = user;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['users', 'stats'] });
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

const roleTabs = computed(() => [
    { value: ALL, label: 'Semua', icon: UsersIcon },
    { value: 'superadmin', label: 'Superadmin', icon: Shield },
    { value: 'admin', label: 'Admin', icon: ShieldCheck },
]);
</script>

<template>
    <Head title="Daftar User" />

    <AppLayout>
        <PageHeader
            title="User Management"
            description="Kelola akun pemilik bisnis yang terdaftar di platform"
            :icon="UsersIcon"
        >
            <template #actions>
                <Button v-if="canCreate" size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah User
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4">
            <TabsPill v-model="filters.role" :tabs="roleTabs" />
        </div>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total User" :value="stats.total ?? 0" tone="brand">
                <template #icon><UsersIcon class="size-5" /></template>
            </StatCard>
            <StatCard label="User Aktif" :value="stats.active ?? 0" tone="brand">
                <template #icon><UserCheck class="size-5" /></template>
            </StatCard>
            <StatCard label="Nonaktif" :value="stats.inactive ?? 0" tone="brand">
                <template #icon><UserX class="size-5" /></template>
            </StatCard>
            <StatCard label="Superadmin" :value="stats.superadmin ?? 0" tone="brand-orange">
                <template #icon><ShieldCheck class="size-5" /></template>
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
                        placeholder="Cari nama atau email di halaman ini…"
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
                        <TableHead class="pl-4">User</TableHead>
                        <TableHead>No. HP</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Dibuat</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="users.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <UsersIcon class="size-7 opacity-40" />
                                <p class="text-sm">Tidak ada user yang cocok dengan filter.</p>
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
                                        @click="openEdit(u)"
                                    >
                                        {{ u.name }}
                                    </button>
                                    <p class="text-xs text-muted-foreground truncate leading-tight">
                                        {{ u.email || u.username }}
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
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground capitalize"
                            >
                                {{ u.role?.name }}
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
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ formatDate(u.created_at) }}
                        </TableCell>
                        <TableCell class="py-3 px-4 text-center whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton
                                    :icon="Pencil"
                                    label="Edit data"
                                    tone="blue"
                                    @click="openEdit(u)"
                                />
                                <ActionButton
                                    :icon="KeyRound"
                                    label="Reset Password & Override"
                                    as-child
                                    tone="indigo"
                                >
                                    <Link :href="route('users.edit', u.id)">
                                        <KeyRound class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
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
                                <ActionButton
                                    :icon="Trash2"
                                    label="Hapus"
                                    tone="red"
                                    @click="openEdit(u)"
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

        <!-- CRUD Modal -->
        <UserFormDialog
            v-model:open="modalOpen"
            :user="editingUser"
            :roles="roles"
            @saved="onSaved"
        />
    </AppLayout>
</template>
