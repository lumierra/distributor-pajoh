<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Loader2,
    LogIn,
    Pencil,
    Plus,
    RotateCcw,
    Ruler,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    units: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['master.unit']?.create,
);
const canUpdate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['master.unit']?.update,
);
const canDelete = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['master.unit']?.delete,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    active:
        props.filters.active === '' || props.filters.active === null || props.filters.active === undefined
            ? ALL
            : String(props.filters.active),
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('units.index'),
            {
                q: filters.q,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.active = ALL;
}

const open = ref(false);
const editing = ref(null);
const isEdit = computed(() => !!editing.value);

const form = useForm({
    name: '',
    is_active: true,
});

function openCreate() {
    editing.value = null;
    form.defaults({ name: '', is_active: true });
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(u) {
    editing.value = u;
    form.defaults({ name: u.name, is_active: !!u.is_active });
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
        form.put(route('units.update', editing.value.id), opts);
    } else {
        form.post(route('units.store'), opts);
    }
}

function destroy(u) {
    if (!window.confirm(`Hapus satuan "${u.name}"?`)) return;
    useForm({}).delete(route('units.destroy', u.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Satuan" />

    <AppLayout>
        <PageHeader
            title="Satuan"
            description="Master satuan global (PCS, KARDUS, LUSIN, dll). Dipakai sebagai pilihan saat input satuan produk."
            :icon="Ruler"
        >
            <template #actions>
                <Button v-if="canCreate" size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Satuan
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama satuan…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Nama Satuan</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="units.data.length === 0">
                        <TableCell colspan="3" class="text-center py-16 text-muted-foreground">
                            <div class="flex flex-col items-center gap-2">
                                <Ruler class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada satuan.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="u in units.data"
                        :key="u.id"
                        class="hover:bg-muted/30 transition-colors"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <p class="font-medium">{{ u.name }}</p>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="u.is_active"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                            >Aktif</span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">Nonaktif</span>
                        </TableCell>
                        <TableCell class="text-center pr-4 whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton
                                    v-if="canUpdate"
                                    :icon="Pencil"
                                    label="Edit"
                                    tone="blue"
                                    @click="openEdit(u)"
                                />
                                <ActionButton
                                    v-if="canDelete"
                                    :icon="Trash2"
                                    label="Hapus"
                                    tone="red"
                                    @click="destroy(u)"
                                />
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="units.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="units" />
            </div>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-[420px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <Ruler class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">
                                {{ isEdit ? `Edit Satuan — ${editing.name}` : 'Tambah Satuan' }}
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Master satuan global. Konversi ke base unit ditentukan per produk.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama Satuan *</Label>
                        <Input v-model="form.name" required class="h-9 uppercase" placeholder="cth: PCS, KARDUS" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                        <Label class="text-xs font-medium block cursor-pointer">Aktif</Label>
                        <Switch v-model="form.is_active" />
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
