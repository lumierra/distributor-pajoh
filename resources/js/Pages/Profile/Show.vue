<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { KeyRound, Loader2, UserCog } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: { type: Object, required: true },
    logins: { type: Array, required: true },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email ?? '',
    phone: props.user.phone ?? '',
    address: props.user.address ?? '',
    city: props.user.city ?? '',
    emergency_contact_name: props.user.emergency_contact_name ?? '',
    emergency_contact_phone: props.user.emergency_contact_phone ?? '',
});

function submit() {
    form.put(route('profile.update'), { preserveScroll: true });
}

function userInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

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
    <Head title="Profil Saya" />

    <AppLayout>
        <PageHeader
            title="Profil Saya"
            description="Update data kontak & alamat akun Anda."
            :icon="UserCog"
        >
            <template #actions>
                <Button as-child variant="outline" size="lg">
                    <Link :href="route('password.change.show')">
                        <KeyRound class="size-4" />
                        Ganti Password
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">
            <!-- Main: form -->
            <form @submit.prevent="submit" class="space-y-6 min-w-0">
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold">Identitas</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Username & role hanya bisa diubah oleh administrator.
                        </p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5">
                        <div class="space-y-1.5">
                            <Label>Nama lengkap</Label>
                            <Input v-model="form.name" required />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Email</Label>
                            <Input v-model="form.email" type="email" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Phone / WhatsApp</Label>
                            <Input v-model="form.phone" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kota</Label>
                            <Input v-model="form.city" />
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Alamat</Label>
                            <Textarea v-model="form.address" rows="2" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kontak darurat — nama</Label>
                            <Input v-model="form.emergency_contact_name" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kontak darurat — phone</Label>
                            <Input v-model="form.emergency_contact_phone" />
                        </div>
                    </div>
                    <footer class="border-t border-border/70 px-5 py-3 flex justify-end bg-muted/20">
                        <Button type="submit" size="lg" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </Button>
                    </footer>
                </section>
            </form>

            <!-- Side: identity card + login history -->
            <aside class="space-y-4">
                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs p-5 space-y-3">
                    <div class="flex items-center gap-3">
                        <Avatar class="size-12">
                            <AvatarFallback class="bg-primary/10 text-primary text-sm font-semibold">
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
                    </div>
                </section>

                <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden">
                    <header class="border-b border-border/70 px-5 py-3.5">
                        <h2 class="text-sm font-semibold">Login Terbaru</h2>
                        <p class="text-[11px] text-muted-foreground mt-0.5">20 entri terakhir.</p>
                    </header>
                    <ul class="divide-y divide-border/70">
                        <li
                            v-for="row in logins"
                            :key="row.id"
                            class="px-5 py-2.5 text-xs flex items-start gap-2.5"
                        >
                            <Badge
                                :variant="row.is_successful ? 'default' : 'destructive'"
                                class="mt-0.5"
                            >
                                {{ row.is_successful ? 'OK' : 'Fail' }}
                            </Badge>
                            <div class="flex-1 min-w-0">
                                <p class="text-foreground leading-tight">
                                    {{ formatDate(row.login_at) }}
                                </p>
                                <p class="text-muted-foreground leading-tight mt-0.5">
                                    {{ row.channel }} · {{ row.ip }}
                                    <span v-if="row.failure_reason"> · {{ row.failure_reason }}</span>
                                </p>
                            </div>
                        </li>
                        <li
                            v-if="logins.length === 0"
                            class="px-5 py-6 text-center text-xs text-muted-foreground"
                        >
                            Belum ada riwayat login.
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
