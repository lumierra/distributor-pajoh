<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { confirm } from '@/Composables/useConfirm';
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

async function destroy(t) {
    if (!(await confirm({ title: `Hapus tipe ${t.name}?`, destructive: true }))) return;
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
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('customers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Customer
                    </Link>
                </Button>
                <Button
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Tipe
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
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
                    <TableRow v-for="t in types" :key="t.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">{{ t.code }}</TableCell>
                        <TableCell>
                            <p class="font-medium text-foreground">{{ t.name }}</p>
                            <p v-if="t.description" class="text-xs text-muted-foreground">{{ t.description }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ t.customers_count }}</TableCell>
                        <TableCell class="text-center">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    t.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        t.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ t.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="pr-4">
                            <div class="flex justify-end">
                                <ActionGroup class="rounded-full">
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
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                            <Tags class="size-5" />
                        </div>
                        <div class="pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">
                                {{ isEdit ? `Edit Tipe — ${editing.name}` : 'Tambah Tipe' }}
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Code huruf besar (A-Z, 0-9, _). Tidak bisa diubah setelah dibuat.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form class="px-6 pb-2 space-y-3.5" @submit.prevent="submit">
                    <div v-if="!isEdit" class="space-y-1">
                        <Label class="text-xs font-medium">Code *</Label>
                        <Input
                            v-model="form.code"
                            required
                            class="h-10 rounded-xl font-mono uppercase"
                            placeholder="WARUNG"
                        />
                        <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama *</Label>
                        <Input v-model="form.name" required class="h-10 rounded-xl" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Deskripsi</Label>
                        <Textarea v-model="form.description" rows="2" class="rounded-xl" />
                    </div>
                    <div class="grid grid-cols-2 gap-3.5">
                        <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                            <Label class="text-xs font-medium block cursor-pointer mb-0">Aktif</Label>
                            <Switch v-model="form.is_active" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Sort order</Label>
                            <Input v-model="form.sort_order" type="number" min="0" class="h-10 rounded-xl" />
                        </div>
                    </div>
                </form>

                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" size="default" class="rounded-full" @click="open = false">
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
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
