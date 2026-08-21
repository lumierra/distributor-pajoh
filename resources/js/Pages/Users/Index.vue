<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Eye,
    LogIn,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Shield,
    ShieldCheck,
    Trash2,
    Users as UsersIcon,
    UserCheck,
    UserX,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
import Pagination from '@/Components/Shared/Pagination.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import UserDetailDialog from '@/Components/Users/UserDetailDialog.vue';
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
    router.reload({ only: ['users', 'stats'] });
}

function onDetailChanged() {
    router.reload({
        only: ['users', 'stats'],
        onSuccess: () => {
            // Re-sync detailUser dgn data terbaru kalau modal masih terbuka
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

async function destroy(user) {
    if (!(await confirm({ title: `Hapus user ${user.name}?`, destructive: true }))) return;
    useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
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

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total User', value: props.stats.total ?? 0, icon: UsersIcon },
        { label: 'User Aktif', value: props.stats.active ?? 0, icon: UserCheck },
        { label: 'Nonaktif', value: props.stats.inactive ?? 0, icon: UserX },
        { label: 'Superadmin', value: props.stats.superadmin ?? 0, icon: ShieldCheck },
    ];
});
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
                <TabsPill v-model="filters.role" :tabs="roleTabs" tone="brand" />
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah User
                </Button>
            </template>
        </PageHeader>

        <!-- ── Stat tiles — soft red tint, angka+label kiri, ikon kanan ── -->
        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div
                v-for="tile in statTiles"
                :key="tile.label"
                class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:ring-brand/25 hover:shadow-md hover:-translate-y-0.5"
            >
                <div class="min-w-0">
                    <p class="text-lg font-semibold tracking-tight leading-tight text-brand-dark">
                        {{ tile.value }}
                    </p>
                    <p class="text-[12px] text-brand-dark/70 truncate leading-tight mt-0.5">
                        {{ tile.label }}
                    </p>
                </div>
                <div
                    class="size-9 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0"
                >
                    <component :is="tile.icon" class="size-4.5" />
                </div>
            </div>
        </section>

        <!-- ── Grouped card: toolbar menyatu + list rows dipisah hairline (bukan tabel) ── -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama atau email di halaman ini…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="default"
                        class="rounded-full"
                        @click="reset"
                    >
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <div v-if="users.data.length === 0" class="px-4 py-16">
                <div class="flex flex-col items-center gap-2 text-muted-foreground">
                    <UsersIcon class="size-7 opacity-40" />
                    <p class="text-sm">Tidak ada user yang cocok dengan filter.</p>
                </div>
            </div>

            <Table v-else class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Dibuat</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="u in users.data"
                        :key="u.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[12px] font-semibold shadow-sm shrink-0"
                                >
                                    {{ userInitials(u.name) }}
                                </div>
                                <button type="button" class="text-left min-w-0" @click="openDetail(u)">
                                    <p class="font-medium text-foreground truncate leading-tight hover:text-primary transition-colors">
                                        {{ u.name }}
                                    </p>
                                    <p class="text-[12px] text-muted-foreground truncate leading-tight mt-0.5">
                                        {{ u.email || u.username }}
                                        <span v-if="u.phone"> · {{ u.phone }}</span>
                                    </p>
                                </button>
                            </div>
                        </TableCell>

                        <TableCell>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-muted/70 text-muted-foreground capitalize"
                            >
                                {{ u.role?.name }}
                            </span>
                        </TableCell>

                        <TableCell>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    u.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        u.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>

                        <TableCell class="text-[12px] text-muted-foreground">
                            {{ formatDate(u.created_at) }}
                        </TableCell>

                        <TableCell class="pr-4 text-center">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton
                                        :icon="Eye"
                                        label="Detail & aksi cepat"
                                        tone="indigo"
                                        @click="openDetail(u)"
                                    />
                                    <ActionButton
                                        :icon="Pencil"
                                        label="Edit data"
                                        tone="blue"
                                        @click="openEdit(u)"
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
                                    <ActionButton
                                        v-if="u.permissions_summary?.canDelete"
                                        :icon="Trash2"
                                        label="Hapus"
                                        tone="red"
                                        @click="destroy(u)"
                                    />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="users.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="users" />
            </div>
        </section>

        <!-- CRUD Modal (Create / Edit data dasar) -->
        <UserFormDialog
            v-model:open="modalOpen"
            :user="editingUser"
            :roles="roles"
            @saved="onSaved"
        />

        <!-- Detail Modal: info + quick actions (reset pwd, force logout, menu override, delete) -->
        <UserDetailDialog
            v-model:open="detailOpen"
            :user="detailUser"
            :can-update="detailUser?.permissions_summary?.canUpdate ?? false"
            :can-reset-password="detailUser?.permissions_summary?.canResetPassword ?? false"
            :can-force-logout="detailUser?.permissions_summary?.canForceLogout ?? false"
            :can-delete="detailUser?.permissions_summary?.canDelete ?? false"
            :can-manage-overrides="detailUser?.permissions_summary?.canManageOverrides ?? false"
            @edit-requested="openEdit"
            @changed="onDetailChanged"
        />
    </AppLayout>
</template>
