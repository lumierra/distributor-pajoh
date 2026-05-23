<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    KeyRound,
    Loader2,
    LogOut,
    ShieldCheck,
    Trash2,
    UserCheck,
    UserCog,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, required: true },
    canUpdate: { type: Boolean, default: false },
    canResetPassword: { type: Boolean, default: false },
    canForceLogout: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canManageOverrides: { type: Boolean, default: false },
});

const isEdit = computed(() => !!props.user);

const form = useForm({
    name: props.user?.name ?? '',
    username: props.user?.username ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    role_id: props.user?.role_id ?? props.roles[0]?.id ?? null,
    is_active: props.user?.is_active ?? true,
    password: '',
    password_confirmation: '',
    address: props.user?.address ?? '',
    city: props.user?.city ?? '',
    nik: props.user?.nik ?? '',
    emergency_contact_name: props.user?.emergency_contact_name ?? '',
    emergency_contact_phone: props.user?.emergency_contact_phone ?? '',
    hire_date: props.user?.hire_date ?? '',
    default_area: props.user?.default_area ?? '',
    monthly_target: props.user?.monthly_target ?? null,
});

function submit() {
    if (isEdit.value) {
        form.put(route('users.update', props.user.id), { preserveScroll: true });
    } else {
        form.post(route('users.store'));
    }
}

const resetForm = useForm({ new_password: '', reason: '' });
const resetOpen = ref(false);
function submitReset() {
    resetForm.post(route('users.reset-password', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.reset();
            resetOpen.value = false;
        },
    });
}

function forceLogout() {
    useForm({}).post(route('users.force-logout', props.user.id), { preserveScroll: true });
}

function toggleActive() {
    useForm({}).post(route('users.toggle-active', props.user.id), { preserveScroll: true });
}

function destroyUser() {
    useForm({}).delete(route('users.destroy', props.user.id));
}

const isSelf = computed(() => usePage().props.auth?.user?.id === props.user?.id);

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
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="isEdit ? `User: ${user.name}` : 'Tambah User'" />

    <AppLayout>
        <PageHeader
            :title="isEdit ? user.name : 'Tambah User'"
            :description="
                isEdit
                    ? `Detail akun & izin akses · ${user.username}`
                    : 'Buat akun user baru. Password awal wajib diganti saat login pertama.'
            "
            :icon="UserCog"
        >
            <template #actions>
                <Button as-child variant="ghost" size="lg">
                    <Link :href="route('users.index')">
                        <ArrowLeft class="size-4" />
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">
            <!-- ── Main column ── -->
            <div class="space-y-6 min-w-0">
                <!-- Card: Identitas -->
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Identitas</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Data primer user.</p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label for="name">Nama lengkap *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                required
                                :disabled="!canUpdate && isEdit"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="username">Username *</Label>
                            <Input
                                id="username"
                                v-model="form.username"
                                required
                                :disabled="!canUpdate && isEdit"
                                class="font-mono"
                            />
                            <p v-if="form.errors.username" class="text-xs text-destructive">
                                {{ form.errors.username }}
                            </p>
                            <p v-else class="text-[11px] text-muted-foreground">
                                Huruf kecil, angka, underscore, titik.
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                v-model="form.email"
                                :disabled="!canUpdate && isEdit"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive">
                                {{ form.errors.email }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="phone">Phone / WhatsApp</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                :disabled="!canUpdate && isEdit"
                            />
                            <p v-if="form.errors.phone" class="text-xs text-destructive">
                                {{ form.errors.phone }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Card: Role & Status -->
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Role &amp; Status</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Role menentukan akses default; user nonaktif tidak bisa login.
                        </p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label>Role *</Label>
                            <Select v-model="form.role_id" :disabled="!canUpdate && isEdit">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in roles" :key="r.id" :value="r.id">
                                        {{ r.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.role_id" class="text-xs text-destructive">
                                {{ form.errors.role_id }}
                            </p>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md ring-1 ring-foreground/10 bg-background px-3.5 py-2.5"
                        >
                            <div>
                                <Label class="block">Status aktif</Label>
                                <p class="text-[11px] text-muted-foreground">
                                    User nonaktif tidak bisa login.
                                </p>
                            </div>
                            <Switch v-model="form.is_active" :disabled="!canUpdate && isEdit" />
                        </div>
                    </div>
                </section>

                <!-- Card: Password (create only) -->
                <section
                    v-if="!isEdit"
                    class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs"
                >
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Password Awal</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Min 8 karakter, harus mengandung huruf &amp; angka. User akan dipaksa
                            ganti saat login pertama.
                        </p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label>Password *</Label>
                            <Input v-model="form.password" type="password" required />
                            <ul
                                v-if="Array.isArray(form.errors.password)"
                                class="text-xs text-destructive list-disc pl-5 space-y-0.5"
                            >
                                <li v-for="msg in form.errors.password" :key="msg">{{ msg }}</li>
                            </ul>
                            <p v-else-if="form.errors.password" class="text-xs text-destructive">
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Konfirmasi password *</Label>
                            <Input v-model="form.password_confirmation" type="password" required />
                        </div>
                    </div>
                </section>

                <!-- Card: Data tambahan -->
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Data Tambahan</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Opsional. Sales pakai default area untuk routing visit.
                        </p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label>NIK</Label>
                            <Input v-model="form.nik" :disabled="!canUpdate && isEdit" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Hire date</Label>
                            <Input
                                v-model="form.hire_date"
                                type="date"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Alamat</Label>
                            <Textarea
                                v-model="form.address"
                                rows="2"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kota</Label>
                            <Input v-model="form.city" :disabled="!canUpdate && isEdit" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Default area (sales)</Label>
                            <Input
                                v-model="form.default_area"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kontak darurat — nama</Label>
                            <Input
                                v-model="form.emergency_contact_name"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kontak darurat — phone</Label>
                            <Input
                                v-model="form.emergency_contact_phone"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Target bulanan (Rp)</Label>
                            <Input
                                v-model="form.monthly_target"
                                type="number"
                                step="0.01"
                                min="0"
                                :disabled="!canUpdate && isEdit"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="ghost" size="lg" as-child>
                        <Link :href="route('users.index')">Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="form.processing || (isEdit && !canUpdate)"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{
                            form.processing
                                ? 'Menyimpan…'
                                : isEdit
                                  ? 'Simpan Perubahan'
                                  : 'Buat User'
                        }}
                    </Button>
                </div>
            </div>

            <!-- ── Side column: identity card + actions ── -->
            <aside class="space-y-4">
                <section
                    v-if="isEdit"
                    class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs p-5 space-y-3"
                >
                    <div class="flex items-center gap-3">
                        <Avatar class="size-12">
                            <AvatarFallback
                                class="bg-primary/10 text-primary text-sm font-semibold"
                            >
                                {{ userInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <p class="font-semibold text-foreground truncate">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground font-mono">
                                @{{ user.username }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge variant="secondary" class="capitalize">{{ user.role?.name }}</Badge>
                        <Badge :variant="user.is_active ? 'default' : 'secondary'">
                            {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                        </Badge>
                        <Badge v-if="user.force_password_change" variant="outline">
                            Wajib ganti pwd
                        </Badge>
                    </div>
                    <dl class="grid grid-cols-1 gap-2 text-xs pt-2 border-t border-border/70">
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground shrink-0">Login terakhir</dt>
                            <dd class="text-foreground text-right">
                                {{ formatDate(user.last_login_at) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground shrink-0">IP</dt>
                            <dd class="text-foreground font-mono">
                                {{ user.last_login_ip || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground shrink-0">Pwd diubah</dt>
                            <dd class="text-foreground text-right">
                                {{ formatDate(user.password_changed_at) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="isEdit"
                    class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden"
                >
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold text-foreground">Quick Actions</h2>
                    </header>
                    <ul class="divide-y divide-border/70">
                        <li v-if="canManageOverrides">
                            <Link
                                :href="route('users.menu-overrides', user.id)"
                                class="flex items-center gap-3 px-5 py-3 hover:bg-muted/50 transition-colors"
                            >
                                <ShieldCheck class="size-4 text-muted-foreground" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium">Menu Override</p>
                                    <p class="text-[11px] text-muted-foreground">
                                        Atur izin per menu di luar role default.
                                    </p>
                                </div>
                            </Link>
                        </li>

                        <li v-if="canResetPassword">
                            <Dialog v-model:open="resetOpen">
                                <DialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-muted/50 transition-colors text-left"
                                    >
                                        <KeyRound class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Reset Password</p>
                                            <p class="text-[11px] text-muted-foreground">
                                                Set password sementara, user dipaksa ganti.
                                            </p>
                                        </div>
                                    </button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[420px]">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Reset password — {{ user.name }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Password sementara yang Anda set akan dipakai saat user
                                            login berikutnya, lalu wajib diganti.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form class="space-y-4" @submit.prevent="submitReset">
                                        <div class="space-y-1.5">
                                            <Label>Password sementara *</Label>
                                            <Input
                                                v-model="resetForm.new_password"
                                                type="text"
                                                required
                                                minlength="8"
                                                placeholder="min. 8 karakter"
                                            />
                                            <p
                                                v-if="resetForm.errors.new_password"
                                                class="text-xs text-destructive"
                                            >
                                                {{ resetForm.errors.new_password }}
                                            </p>
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label>Alasan (opsional)</Label>
                                            <Textarea v-model="resetForm.reason" rows="2" />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                size="lg"
                                                :disabled="resetForm.processing"
                                            >
                                                <Loader2
                                                    v-if="resetForm.processing"
                                                    class="size-4 animate-spin"
                                                />
                                                Reset Password
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </li>

                        <li v-if="canForceLogout">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-muted/50 transition-colors text-left"
                                    >
                                        <LogOut class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Force Logout</p>
                                            <p class="text-[11px] text-muted-foreground">
                                                Cabut semua sesi web &amp; token mobile.
                                            </p>
                                        </div>
                                    </button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Force logout {{ user.name }}?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Semua sesi web dan token Sanctum mobile user akan
                                            dicabut. User harus login ulang di semua device.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction @click="forceLogout">
                                            Force Logout
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </li>

                        <li v-if="canUpdate && !isSelf">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-muted/50 transition-colors text-left"
                                    >
                                        <UserCheck class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">
                                                {{
                                                    user.is_active
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'
                                                }}
                                                user
                                            </p>
                                            <p class="text-[11px] text-muted-foreground">
                                                {{
                                                    user.is_active
                                                        ? 'User tidak bisa login lagi.'
                                                        : 'User bisa login lagi.'
                                                }}
                                            </p>
                                        </div>
                                    </button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            {{
                                                user.is_active ? 'Nonaktifkan' : 'Aktifkan'
                                            }}
                                            {{ user.name }}?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription v-if="user.is_active">
                                            User tidak bisa login lagi. Anda bisa aktifkan kembali
                                            kapan saja.
                                        </AlertDialogDescription>
                                        <AlertDialogDescription v-else>
                                            User bisa login lagi mulai sekarang.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction @click="toggleActive">
                                            {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </li>

                        <li v-if="canDelete && !isSelf">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-destructive/5 transition-colors text-left text-destructive"
                                    >
                                        <Trash2 class="size-4" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Hapus User</p>
                                            <p class="text-[11px] opacity-70">
                                                Soft-delete; bisa di-restore lewat Trash.
                                            </p>
                                        </div>
                                    </button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle class="flex items-center gap-2">
                                            <AlertTriangle class="size-4 text-destructive" />
                                            Hapus user {{ user.name }}?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            User akan di-soft-delete. Anda bisa restore lewat
                                            halaman Trash. Data terkait (login history, activity
                                            log) tetap dipertahankan.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction
                                            class="bg-destructive/15 text-destructive hover:bg-destructive/25"
                                            @click="destroyUser"
                                        >
                                            Hapus
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </li>
                    </ul>
                </section>
            </aside>
        </form>
    </AppLayout>
</template>
