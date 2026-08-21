<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Info } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    opname_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

function submit() {
    form.post(route('opnames.store'));
}
</script>

<template>
    <Head title="Mulai Stock Opname" />

    <AppLayout>
        <PageHeader
            title="Mulai Stock Opname"
            description="Sesi baru akan menarik semua batch berstok untuk dihitung fisik."
            :icon="ClipboardCheck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('opnames.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Opname
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="max-w-xl">
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 mb-4 flex items-start gap-3 text-sm">
                <Info class="size-5 text-amber-700 shrink-0 mt-0.5" />
                <p class="text-amber-900">
                    Saat sesi dibuat, sistem otomatis menarik <strong>semua batch yang punya stok</strong> beserta
                    jumlah menurut sistem. Di halaman berikutnya Anda tinggal mengisi <strong>hasil hitung fisik</strong>
                    per baris.
                </p>
            </div>

            <form class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5" @submit.prevent="submit">
                <div class="space-y-4">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tanggal Opname *</Label>
                        <Input v-model="form.opname_date" type="date" required class="h-10 rounded-xl" />
                        <p v-if="form.errors.opname_date" class="text-xs text-destructive">{{ form.errors.opname_date }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" class="rounded-xl" placeholder="Mis. Opname akhir bulan / lokasi rak A (opsional)" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                        <Link :href="route('opnames.index')">Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        size="default"
                        class="rounded-full bg-brand text-white hover:bg-brand-dark"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Membuat…' : 'Buat & Mulai Hitung' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
