<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, ShieldCheck, UserPlus } from '@lucide/vue';
import { computed, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = create mode, object = edit mode */
    user: { type: Object, default: null },
    roles: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.user);

/**
 * /users hanya untuk role NON-sales. Sales punya halaman tersendiri
 * /sales-users karena butuh field tambahan (area, target, device).
 */
const assignableRoles = computed(() =>
    props.roles.filter((r) => r.code !== 'sales'),
);

const form = useForm({
    name: '',
    username: '',
    email: '',
    phone: '',
    role_id: props.roles[0]?.id ?? null,
    is_active: true,
    password: '',
    password_confirmation: '',
});

// Reset form ke user data ketika modal dibuka / user prop berubah
watch(
    () => [props.open, props.user?.id],
    ([open]) => {
        if (!open) return;
        if (props.user) {
            form.defaults({
                name: props.user.name ?? '',
                username: props.user.username ?? '',
                email: props.user.email ?? '',
                phone: props.user.phone ?? '',
                role_id: props.user.role_id,
                is_active: !!props.user.is_active,
                password: '',
                password_confirmation: '',
            });
        } else {
            form.defaults({
                name: '',
                username: '',
                email: '',
                phone: '',
                role_id: props.roles[0]?.id ?? null,
                is_active: true,
                password: '',
                password_confirmation: '',
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('users.update', props.user.id), opts);
    } else {
        form.post(route('users.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden rounded-3xl gap-0">
            <!-- Header minimal — ikon bulat soft, tanpa kotak tegas -->
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div
                        class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0"
                    >
                        <component :is="isEdit ? ShieldCheck : UserPlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit User — ${user.name}` : 'Tambah User' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? 'Update data user. Password diatur lewat aksi terpisah.'
                                    : 'Buat akun user baru. Password awal wajib diganti saat login pertama.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Form (scrollable kalau panjang) -->
            <form
                class="px-6 pb-2 space-y-4 max-h-[65vh] overflow-y-auto"
                @submit.prevent="submit"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1 sm:col-span-2">
                        <Label for="name" class="text-xs font-medium">Nama lengkap *</Label>
                        <Input id="name" v-model="form.name" required class="h-10 rounded-xl" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Role *</Label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button
                                v-for="r in assignableRoles"
                                :key="r.id"
                                type="button"
                                :class="[
                                    'rounded-full px-2 py-1.5 text-xs font-medium text-center transition-all ring-1 truncate',
                                    form.role_id === r.id
                                        ? 'bg-brand text-white ring-brand shadow-sm'
                                        : 'bg-muted/50 text-foreground ring-transparent hover:bg-muted',
                                ]"
                                :title="r.name"
                                @click="form.role_id = r.id"
                            >
                                {{ r.name }}
                            </button>
                        </div>
                        <p v-if="form.errors.role_id" class="text-xs text-destructive">
                            {{ form.errors.role_id }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label for="username" class="text-xs font-medium">Username *</Label>
                        <Input
                            id="username"
                            v-model="form.username"
                            required
                            class="h-10 rounded-xl font-mono"
                        />
                        <p v-if="form.errors.username" class="text-xs text-destructive">
                            {{ form.errors.username }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label for="email" class="text-xs font-medium">Email</Label>
                        <Input id="email" type="email" v-model="form.email" class="h-10 rounded-xl" />
                        <p v-if="form.errors.email" class="text-xs text-destructive">
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label for="phone" class="text-xs font-medium">No. HP / WhatsApp</Label>
                        <Input id="phone" v-model="form.phone" class="h-10 rounded-xl" />
                        <p v-if="form.errors.phone" class="text-xs text-destructive">
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <!-- Password section (create only) -->
                <div v-if="!isEdit" class="space-y-2.5 pt-3 border-t border-foreground/5">
                    <p class="text-[12px] text-muted-foreground">
                        Kosongkan untuk pakai password default
                        <code class="font-mono px-1.5 py-0.5 rounded-md bg-muted">12345678</code>.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Password</Label>
                            <Input
                                v-model="form.password"
                                type="password"
                                class="h-10 rounded-xl"
                                placeholder="default: 12345678"
                            />
                            <ul
                                v-if="Array.isArray(form.errors.password)"
                                class="text-xs text-destructive list-disc pl-4 space-y-0.5"
                            >
                                <li v-for="msg in form.errors.password" :key="msg">{{ msg }}</li>
                            </ul>
                            <p v-else-if="form.errors.password" class="text-xs text-destructive">
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Konfirmasi password</Label>
                            <Input
                                v-model="form.password_confirmation"
                                type="password"
                                class="h-10 rounded-xl"
                                placeholder="ulangi bila diisi"
                            />
                        </div>
                    </div>
                </div>

                <!-- Active toggle -->
                <div
                    class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3"
                >
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer mb-0"
                            >Status aktif</Label
                        >
                        <p class="text-[12px] text-muted-foreground mt-0.5">
                            User nonaktif tidak bisa login.
                        </p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>
            </form>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    class="rounded-full"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Simpan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
