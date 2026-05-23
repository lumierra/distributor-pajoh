<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
import { Textarea } from '@/Components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    types: { type: Array, required: true },
});

const open = ref(false);
const editing = ref(null);
const isEdit = computed(() => !!editing.value);

const form = useForm({
    code: '',
    name: '',
    description: '',
    is_active: true,
    sort_order: 99,
});

function openCreate() {
    editing.value = null;
    form.defaults({ code: '', name: '', description: '', is_active: true, sort_order: 99 });
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(t) {
    editing.value = t;
    form.defaults({
        code: t.code,
        name: t.name,
        description: t.description ?? '',
        is_active: !!t.is_active,
        sort_order: t.sort_order ?? 99,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };
    if (isEdit.value) {
        form.put(route('customer-types.update', editing.value.id), opts);
    } else {
        form.post(route('customer-types.store'), opts);
    }
}

function destroy(t) {
    if (!window.confirm(`Hapus tipe ${t.name}?`)) return;
    useForm({}).delete(route('customer-types.destroy', t.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Tipe Customer" />

    <AppLayout>
        <PageHeader
            title="Tipe Customer"
            description="Master tipe outlet (Warung, Mini Market, Hotel, dll). Beda dari Price Tier."
            :icon="Tags"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('customers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Customer
                    </Link>
                </Button>
                <Button size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Tipe
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Kode</TableHead>
                        <TableHead>Nama</TableHead>
                        <TableHead class="text-right">Customer</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="types.length === 0">
                        <TableCell colspan="5" class="text-center py-16 text-muted-foreground">
                            Belum ada tipe.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="t in types" :key="t.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">{{ t.code }}</TableCell>
                        <TableCell>
                            <p class="font-medium text-foreground">{{ t.name }}</p>
                            <p v-if="t.description" class="text-xs text-muted-foreground">{{ t.description }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ t.customers_count }}</TableCell>
                        <TableCell class="text-center">
                            <span
                                v-if="t.is_active"
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
                        <TableCell class="pr-4">
                            <div class="flex justify-end">
                                <ActionGroup>
                                    <ActionButton :icon="Pencil" label="Edit" tone="blue" @click="openEdit(t)" />
                                    <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroy(t)" />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <Tags class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">
                                {{ isEdit ? `Edit Tipe — ${editing.name}` : 'Tambah Tipe' }}
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Code huruf besar (A-Z, 0-9, _). Tidak bisa diubah setelah dibuat.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                    <div v-if="!isEdit" class="space-y-1">
                        <Label class="text-xs font-medium">Code *</Label>
                        <Input v-model="form.code" required class="h-9 font-mono uppercase" placeholder="WARUNG" />
                        <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama *</Label>
                        <Input v-model="form.name" required class="h-9" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Deskripsi</Label>
                        <Textarea v-model="form.description" rows="2" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                            <Label class="text-xs font-medium block cursor-pointer">Aktif</Label>
                            <Switch v-model="form.is_active" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Sort order</Label>
                            <Input v-model="form.sort_order" type="number" min="0" class="h-9" />
                        </div>
                    </div>
                </form>

                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" size="default" @click="open = false">Batal</Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="default"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
