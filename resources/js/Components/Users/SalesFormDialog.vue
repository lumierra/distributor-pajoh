<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Smartphone, UserCog, UserPlus } from '@lucide/vue';
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
    /** null = create, object = edit */
    user: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.user);

const form = useForm({
    name: '',
    username: '',
    email: '',
    phone: '',
    is_active: true,
    password: '',
    password_confirmation: '',
    device_uuid: '',
    device_name: '',
    mac_address: '',
});

watch(
    () => [props.open, props.user?.id],
    ([open]) => {
        if (!open) return;
        if (props.user) {
            const dev = props.user.primary_device ?? null;
            form.defaults({
                name: props.user.name ?? '',
                username: props.user.username ?? '',
                email: props.user.email ?? '',
                phone: props.user.phone ?? '',
                is_active: !!props.user.is_active,
                password: '',
                password_confirmation: '',
                device_uuid: dev?.device_uuid ?? '',
                device_name: dev?.device_name ?? '',
                mac_address: dev?.mac_address ?? '',
            });
        } else {
            form.defaults({
                name: '',
                username: '',
                email: '',
                phone: '',
                is_active: true,
                password: '',
                password_confirmation: '',
                device_uuid: '',
                device_name: '',
                mac_address: '',
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
        form.put(route('sales-users.update', props.user.id), opts);
    } else {
        form.post(route('sales-users.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[560px] p-0 overflow-hidden rounded-3xl gap-0">
            <!-- Header minimal — ikon bulat soft, tanpa kotak tegas -->
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div
                        class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0"
                    >
                        <component :is="isEdit ? UserCog : UserPlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit Sales — ${user.name}` : 'Tambah Sales' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            <template v-if="isEdit">
                                Ubah data dasar sales. Untuk reset password / kelola device, gunakan menu lain.
                            </template>
                            <template v-else>
                                Buat akun sales lapangan. Role otomatis di-set ke Sales. Atur produk yang
                                boleh dijual lewat menu <strong>Product Group</strong>.
                            </template>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form
                class="px-6 pb-2 space-y-5 max-h-[70vh] overflow-y-auto"
                @submit.prevent="submit"
            >
                <!-- ── Section: Identitas ── -->
                <section class="space-y-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Identitas
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label for="sales-name" class="text-xs font-medium">Nama lengkap *</Label>
                            <Input id="sales-name" v-model="form.name" required class="h-10 rounded-xl" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label for="sales-username" class="text-xs font-medium">Username *</Label>
                            <Input
                                id="sales-username"
                                v-model="form.username"
                                required
                                class="h-10 rounded-xl font-mono"
                            />
                            <p v-if="form.errors.username" class="text-xs text-destructive">
                                {{ form.errors.username }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label for="sales-email" class="text-xs font-medium">Email</Label>
                            <Input id="sales-email" v-model="form.email" type="email" class="h-10 rounded-xl" />
                            <p v-if="form.errors.email" class="text-xs text-destructive">
                                {{ form.errors.email }}
                            </p>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <Label for="sales-phone" class="text-xs font-medium">No. HP / WhatsApp</Label>
                            <Input id="sales-phone" v-model="form.phone" class="h-10 rounded-xl" />
                            <p v-if="form.errors.phone" class="text-xs text-destructive">
                                {{ form.errors.phone }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ── Section: Password ── -->
                <section class="space-y-2.5 pt-3 border-t border-foreground/5">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Password
                    </h3>
                    <p v-if="!isEdit" class="text-[12px] text-muted-foreground">
                        Kosongkan untuk pakai password default
                        <code class="font-mono px-1.5 py-0.5 rounded-md bg-muted">12345678</code>.
                    </p>
                    <p v-else class="text-[12px] text-muted-foreground">
                        Kosongkan kalau tidak ingin mengubah password.
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
                            <Input v-model="form.password_confirmation" type="password" class="h-10 rounded-xl" />
                        </div>
                    </div>
                </section>

                <!-- ── Section: Pre-register / Update Device ── -->
                <section class="space-y-3 pt-3 border-t border-foreground/5">
                    <div class="flex items-center gap-2">
                        <Smartphone class="size-3.5 text-muted-foreground" />
                        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ isEdit ? 'Device sales' : 'Pre-register Device (opsional)' }}
                        </h3>
                    </div>
                    <p v-if="!isEdit" class="text-[12px] text-muted-foreground">
                        Isi kalau admin sudah pegang HP sales-nya. Sales tidak perlu request approval
                        device saat pertama login. OS default <strong>Android</strong>.
                    </p>
                    <p v-else class="text-[12px] text-muted-foreground">
                        Ubah UUID / nama / MAC device aktif sales. Kosongkan UUID untuk membiarkan
                        device existing tidak terubah.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label for="dev-uuid" class="text-xs font-medium">Device UUID</Label>
                            <Input
                                id="dev-uuid"
                                v-model="form.device_uuid"
                                class="h-10 rounded-xl font-mono text-xs"
                                placeholder="cth: 9774d56d682e549c (dari Capacitor Device plugin)"
                            />
                            <p v-if="form.errors.device_uuid" class="text-xs text-destructive">
                                {{ form.errors.device_uuid }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Nama device</Label>
                            <Input v-model="form.device_name" class="h-10 rounded-xl" placeholder="cth: Galaxy A52" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">MAC address</Label>
                            <Input
                                v-model="form.mac_address"
                                class="h-10 rounded-xl font-mono text-xs"
                                placeholder="cth: AA:BB:CC:DD:EE:FF"
                            />
                        </div>
                    </div>
                </section>

                <!-- ── Status aktif ── -->
                <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer mb-0">Status aktif</Label>
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
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Simpan Sales' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
