<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    Eye,
    Layers,
    Loader2,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
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
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    groups: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin
        || page.props.permissions?.['master.product_group']?.create,
);
const canUpdate = computed(
    () => page.props.auth?.user?.is_superadmin
        || page.props.permissions?.['master.product_group']?.update,
);
const canDelete = computed(
    () => page.props.auth?.user?.is_superadmin
        || page.props.permissions?.['master.product_group']?.delete,
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
            route('product-groups.index'),
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

function openEdit(g) {
    editing.value = g;
    form.defaults({
        code: g.code,
        name: g.name,
        description: g.description ?? '',
        is_active: !!g.is_active,
        sort_order: g.sort_order ?? 99,
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
        form.put(route('product-groups.update', editing.value.id), opts);
    } else {
        form.post(route('product-groups.store'), opts);
    }
}

async function destroy(g) {
    if (!(await confirm({ title: `Hapus Product Group "${g.name}"?`, destructive: true }))) return;
    useForm({}).delete(route('product-groups.destroy', g.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Product Group" />

    <AppLayout>
        <PageHeader
            title="Product Group"
            description="Bucket produk yang menentukan apa yang boleh dijual oleh tiap sales. Beda supplier / beda limit → bikin group terpisah."
            :icon="Layers"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Group
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari kode / nama group…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="default"
                        class="rounded-full"
                        @click="reset"
                    >
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">Kode</TableHead>
                        <TableHead>Nama Group</TableHead>
                        <TableHead class="text-right">Produk</TableHead>
                        <TableHead class="text-right">Sales</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="groups.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16 text-muted-foreground">
                            <div class="flex flex-col items-center gap-2">
                                <Layers class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada Product Group.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="g in groups.data"
                        :key="g.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">{{ g.code }}</TableCell>
                        <TableCell>
                            <Link
                                :href="route('product-groups.show', g.id)"
                                class="font-medium text-foreground hover:text-primary transition-colors"
                            >
                                {{ g.name }}
                            </Link>
                            <p v-if="g.description" class="text-xs text-muted-foreground line-clamp-1">
                                {{ g.description }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right">
                            <span class="inline-flex items-center gap-1 text-xs">
                                <Boxes class="size-3 text-muted-foreground" />
                                {{ g.products_count }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <span class="inline-flex items-center gap-1 text-xs">
                                <Users class="size-3 text-muted-foreground" />
                                {{ g.sales_users_count }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    g.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        g.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ g.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center pr-4 whitespace-nowrap">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton
                                        :icon="Eye"
                                        label="Detail & assignment"
                                        tone="indigo"
                                        @click="$inertia.visit(route('product-groups.show', g.id))"
                                    />
                                    <ActionButton
                                        v-if="canUpdate"
                                        :icon="Pencil"
                                        label="Edit"
                                        tone="blue"
                                        @click="openEdit(g)"
                                    />
                                    <ActionButton
                                        v-if="canDelete"
                                        :icon="Trash2"
                                        label="Hapus"
                                        tone="red"
                                        @click="destroy(g)"
                                    />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="groups.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="groups" />
            </div>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                            <Layers class="size-5" />
                        </div>
                        <div class="pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">
                                {{ isEdit ? `Edit Group — ${editing.name}` : 'Tambah Product Group' }}
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
                            placeholder="SUPPA_FROZEN"
                        />
                        <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama *</Label>
                        <Input
                            v-model="form.name"
                            required
                            class="h-10 rounded-xl"
                            placeholder="cth: Supplier A — Frozen"
                        />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Deskripsi</Label>
                        <Textarea
                            v-model="form.description"
                            rows="2"
                            class="rounded-xl"
                            placeholder="Catatan internal mis. range produk / supplier"
                        />
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
