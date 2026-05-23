<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Loader2, Shield, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
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
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    role: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const isEdit = computed(() => !!props.role);

const form = useForm({
    code: props.role?.code ?? '',
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    is_active: props.role?.is_active ?? true,
    sort_order: props.role?.sort_order ?? 99,
});

function submit() {
    if (isEdit.value) {
        form.put(route('roles.update', props.role.id), { preserveScroll: true });
    } else {
        form.post(route('roles.store'));
    }
}

function destroyRole() {
    useForm({}).delete(route('roles.destroy', props.role.id));
}
</script>

<template>
    <Head :title="isEdit ? `Role: ${role.name}` : 'Tambah Role'" />

    <AppLayout>
        <PageHeader
            :title="isEdit ? role.name : 'Tambah Role'"
            :description="
                isEdit
                    ? `Metadata role · ${role.code}`
                    : 'Buat role custom baru. Permission diatur setelah disimpan.'
            "
            :icon="Shield"
        >
            <template #actions>
                <Button as-child variant="ghost" size="lg">
                    <Link :href="route('roles.index')">
                        <ArrowLeft class="size-4" />
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit" class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">
            <!-- Main -->
            <div class="space-y-6 min-w-0">
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold">Identitas Role</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Code dipakai internal; tidak bisa diubah untuk system role.
                        </p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label>Code *</Label>
                            <Input
                                v-model="form.code"
                                required
                                :disabled="isEdit && role.is_system"
                                class="font-mono"
                            />
                            <p v-if="form.errors.code" class="text-xs text-destructive">
                                {{ form.errors.code }}
                            </p>
                            <p v-else class="text-[11px] text-muted-foreground">
                                Huruf kecil + underscore. Tidak bisa diubah untuk system role.
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Nama *</Label>
                            <Input
                                v-model="form.name"
                                required
                                :disabled="isEdit && !canUpdate"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Deskripsi</Label>
                            <Textarea
                                v-model="form.description"
                                rows="3"
                                :disabled="isEdit && !canUpdate"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md ring-1 ring-foreground/10 bg-background px-3.5 py-2.5"
                        >
                            <div>
                                <Label class="block">Aktif</Label>
                                <p class="text-[11px] text-muted-foreground">
                                    Role nonaktif tidak bisa di-assign ke user baru.
                                </p>
                            </div>
                            <Switch v-model="form.is_active" :disabled="isEdit && !canUpdate" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Sort order</Label>
                            <Input
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                :disabled="isEdit && !canUpdate"
                            />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="ghost" size="lg" as-child>
                        <Link :href="route('roles.index')">Batal</Link>
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
                                  : 'Buat Role'
                        }}
                    </Button>
                </div>
            </div>

            <!-- Side actions -->
            <aside v-if="isEdit" class="space-y-4">
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs p-5 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-foreground">{{ role.name }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">
                                {{ role.code }}
                            </p>
                        </div>
                        <Badge :variant="role.is_system ? 'secondary' : 'default'">
                            {{ role.is_system ? 'System' : 'Custom' }}
                        </Badge>
                    </div>
                    <p v-if="role.is_system" class="text-[11px] text-muted-foreground">
                        System role tidak bisa di-rename atau dihapus.
                    </p>
                </section>

                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold">Quick Actions</h2>
                    </header>
                    <ul class="divide-y divide-border/70">
                        <li>
                            <Link
                                :href="route('roles.permissions.edit', role.id)"
                                class="flex items-center gap-3 px-5 py-3 hover:bg-muted/50 transition-colors"
                            >
                                <ShieldCheck class="size-4 text-muted-foreground" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium">Edit Permission</p>
                                    <p class="text-[11px] text-muted-foreground">
                                        Atur izin per menu untuk role ini.
                                    </p>
                                </div>
                            </Link>
                        </li>
                        <li v-if="canDelete">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-destructive/5 transition-colors text-left text-destructive"
                                    >
                                        <Trash2 class="size-4" />
                                        <div class="flex-1">
                                            <p class="text-sm font-medium">Hapus Role</p>
                                            <p class="text-[11px] opacity-70">
                                                Hanya jika tidak ada user assigned.
                                            </p>
                                        </div>
                                    </button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle class="flex items-center gap-2">
                                            <AlertTriangle class="size-4 text-destructive" />
                                            Hapus role {{ role.name }}?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Role hanya bisa dihapus jika tidak ada user yang
                                            assigned. Tindakan ini tidak bisa dibatalkan.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Batal</AlertDialogCancel>
                                        <AlertDialogAction
                                            class="bg-destructive/15 text-destructive hover:bg-destructive/25"
                                            @click="destroyRole"
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
