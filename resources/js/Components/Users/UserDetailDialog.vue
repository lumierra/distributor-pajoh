<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    KeyRound,
    Loader2,
    LogOut,
    Pencil,
    ShieldCheck,
    Trash2,
    UserCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    user: { type: Object, default: null },
    canResetPassword: { type: Boolean, default: false },
    canForceLogout: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canManageOverrides: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'edit-requested', 'changed']);

const isSelf = computed(() => usePage().props.auth?.user?.id === props.user?.id);

function close() {
    emit('update:open', false);
}

function notifyChanged() {
    emit('changed');
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
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ── Reset Password ──
const resetForm = useForm({ new_password: '', reason: '' });
const resetOpen = ref(false);
function submitReset() {
    resetForm.post(route('users.reset-password', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.reset();
            resetOpen.value = false;
            notifyChanged();
        },
    });
}

// ── Force Logout ──
function forceLogout() {
    useForm({}).post(route('users.force-logout', props.user.id), {
        preserveScroll: true,
        onSuccess: notifyChanged,
    });
}

// ── Toggle Active ──
function toggleActive() {
    useForm({}).post(route('users.toggle-active', props.user.id), {
        preserveScroll: true,
        onSuccess: notifyChanged,
    });
}

// ── Delete ──
function destroyUser() {
    useForm({}).delete(route('users.destroy', props.user.id), {
        onSuccess: () => {
            close();
            notifyChanged();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <DialogTitle class="text-base font-semibold">Detail User</DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Info & aksi cepat untuk user ini.
                </DialogDescription>
            </DialogHeader>

            <div v-if="user" class="max-h-[75vh] overflow-y-auto">
                <!-- Profile card -->
                <section class="mx-6 mb-4 space-y-3 rounded-2xl bg-muted/40 px-4 py-4">
                    <div class="flex items-center gap-3">
                        <Avatar class="size-12">
                            <AvatarFallback class="bg-primary text-primary-foreground text-sm font-semibold">
                                {{ userInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground truncate">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground font-mono">@{{ user.username }}</p>
                            <p v-if="user.email" class="text-[12px] text-muted-foreground truncate mt-0.5">
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <Badge variant="secondary" class="capitalize rounded-full">{{ user.role?.name }}</Badge>
                        <Badge :variant="user.is_active ? 'default' : 'secondary'" class="rounded-full">
                            {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                        </Badge>
                        <Badge v-if="user.force_password_change" variant="outline" class="rounded-full">
                            Wajib ganti pwd
                        </Badge>
                    </div>

                    <dl class="grid grid-cols-1 gap-1.5 text-xs pt-3 border-t border-foreground/5">
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Login terakhir</dt>
                            <dd class="text-foreground text-right">{{ formatDate(user.last_login_at) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">IP</dt>
                            <dd class="text-foreground font-mono text-right">{{ user.last_login_ip || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">No. HP</dt>
                            <dd class="text-foreground font-mono text-right">{{ user.phone || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Pwd diubah</dt>
                            <dd class="text-foreground text-right">{{ formatDate(user.password_changed_at) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Quick Actions -->
                <section>
                    <header class="px-6 pb-2">
                        <h3 class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                            Quick Actions
                        </h3>
                    </header>
                    <ul class="mx-6 mb-6 divide-y divide-foreground/5 rounded-2xl bg-muted/40 overflow-hidden">
                        <!-- Edit data -->
                        <li v-if="canUpdate">
                            <button
                                type="button"
                                class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-foreground/5 transition-colors text-left"
                                @click="$emit('edit-requested', user); close()"
                            >
                                <Pencil class="size-4 text-muted-foreground" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium">Edit data dasar</p>
                                    <p class="text-[12px] text-muted-foreground">Nama, username, email, role, no HP.</p>
                                </div>
                            </button>
                        </li>

                        <!-- Menu Overrides -->
                        <li v-if="canManageOverrides">
                            <Link
                                :href="route('users.menu-overrides', user.id)"
                                class="flex items-center gap-3 px-4 py-3.5 hover:bg-foreground/5 transition-colors"
                            >
                                <ShieldCheck class="size-4 text-muted-foreground" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium">Menu Override</p>
                                    <p class="text-[12px] text-muted-foreground">
                                        Atur izin per menu di luar role default.
                                    </p>
                                </div>
                            </Link>
                        </li>

                        <!-- Reset Password -->
                        <li v-if="canResetPassword">
                            <Dialog v-model:open="resetOpen">
                                <DialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-foreground/5 transition-colors text-left"
                                    >
                                        <KeyRound class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Reset Password</p>
                                            <p class="text-[12px] text-muted-foreground">
                                                Set password sementara, user dipaksa ganti.
                                            </p>
                                        </div>
                                    </button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[420px] rounded-3xl">
                                    <DialogHeader>
                                        <DialogTitle>Reset password — {{ user.name }}</DialogTitle>
                                        <DialogDescription>
                                            Password sementara yang Anda set akan dipakai saat user login berikutnya,
                                            lalu wajib diganti.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form class="space-y-3" @submit.prevent="submitReset">
                                        <div class="space-y-1.5">
                                            <Label>Password sementara *</Label>
                                            <Input
                                                v-model="resetForm.new_password"
                                                type="text"
                                                required
                                                minlength="8"
                                                placeholder="min. 8 karakter"
                                                class="h-10 rounded-xl"
                                            />
                                            <p v-if="resetForm.errors.new_password" class="text-xs text-destructive">
                                                {{ resetForm.errors.new_password }}
                                            </p>
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label>Alasan (opsional)</Label>
                                            <Textarea v-model="resetForm.reason" rows="2" class="rounded-xl" />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                class="rounded-full"
                                                :disabled="resetForm.processing"
                                            >
                                                <Loader2 v-if="resetForm.processing" class="size-4 animate-spin" />
                                                Reset Password
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </li>

                        <!-- Force Logout -->
                        <li v-if="canForceLogout">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-foreground/5 transition-colors text-left"
                                    >
                                        <LogOut class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Force Logout</p>
                                            <p class="text-[12px] text-muted-foreground">
                                                Cabut semua sesi web &amp; token mobile.
                                            </p>
                                        </div>
                                    </button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Force logout {{ user.name }}?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Semua sesi web dan token Sanctum mobile user akan dicabut. User harus
                                            login ulang di semua device.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction @click="forceLogout">Force Logout</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </li>

                        <!-- Toggle Active -->
                        <li v-if="canUpdate && !isSelf">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-foreground/5 transition-colors text-left"
                                    >
                                        <UserCheck class="size-4 text-muted-foreground" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">
                                                {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }} user
                                            </p>
                                            <p class="text-[12px] text-muted-foreground">
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
                                            {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ user.name }}?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription v-if="user.is_active">
                                            User tidak bisa login lagi. Anda bisa aktifkan kembali kapan saja.
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

                        <!-- Soft Delete -->
                        <li v-if="canDelete && !isSelf">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-destructive/5 transition-colors text-left text-destructive"
                                    >
                                        <Trash2 class="size-4" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Hapus User</p>
                                            <p class="text-[12px] opacity-70">
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
                                            User akan di-soft-delete. Anda bisa restore lewat halaman Trash.
                                            Data terkait (login history, activity log) tetap dipertahankan.
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
            </div>

            <DialogFooter class="px-6 py-4">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="close">
                    Tutup
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
