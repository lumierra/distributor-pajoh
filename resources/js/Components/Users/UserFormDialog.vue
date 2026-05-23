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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = create mode, object = edit mode */
    user: { type: Object, default: null },
    roles: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.user);

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
        <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden">
            <!-- Header dengan icon kotak hijau (mirror screenshot pos-pajoh) -->
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200"
                    >
                        <component :is="isEdit ? ShieldCheck : UserPlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
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
                class="px-5 py-4 space-y-3.5 max-h-[65vh] overflow-y-auto"
                @submit.prevent="submit"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1 sm:col-span-2">
                        <Label for="name" class="text-xs font-medium">Nama lengkap *</Label>
                        <Input id="name" v-model="form.name" required class="h-9" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label for="username" class="text-xs font-medium">Username *</Label>
                        <Input
                            id="username"
                            v-model="form.username"
                            required
                            class="h-9 font-mono"
                        />
                        <p v-if="form.errors.username" class="text-xs text-destructive">
                            {{ form.errors.username }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Role *</Label>
                        <Select v-model="form.role_id">
                            <SelectTrigger class="h-9">
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
                    <div class="space-y-1">
                        <Label for="email" class="text-xs font-medium">Email</Label>
                        <Input id="email" type="email" v-model="form.email" class="h-9" />
                        <p v-if="form.errors.email" class="text-xs text-destructive">
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label for="phone" class="text-xs font-medium">No. HP / WhatsApp</Label>
                        <Input id="phone" v-model="form.phone" class="h-9" />
                        <p v-if="form.errors.phone" class="text-xs text-destructive">
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <!-- Password section (create only) -->
                <div v-if="!isEdit" class="space-y-3 pt-2 border-t border-border/70">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Password *</Label>
                            <Input v-model="form.password" type="password" required class="h-9" />
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
                            <Label class="text-xs font-medium">Konfirmasi password *</Label>
                            <Input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                class="h-9"
                            />
                        </div>
                    </div>
                </div>

                <!-- Active toggle -->
                <div
                    class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5"
                >
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer"
                            >Status aktif</Label
                        >
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            User nonaktif tidak bisa login.
                        </p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>

                <!-- Info hint (yellow) -->
                <div
                    v-if="!isEdit"
                    class="rounded-md bg-warning-soft ring-1 ring-warning/20 px-3 py-2 text-xs flex items-start gap-2"
                >
                    <span class="text-base">💡</span>
                    <p class="text-foreground/80 leading-relaxed">
                        User akan dipaksa <strong>ganti password</strong> saat login pertama. Pastikan No. HP
                        terisi agar notifikasi WhatsApp dapat dikirim otomatis (saat T18 aktif).
                    </p>
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
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
