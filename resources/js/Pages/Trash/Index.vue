<script setup>
import { Head, router } from '@inertiajs/vue3';
import { RotateCcw, Trash2 } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
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
    type: { type: String, required: true },
    label: { type: String, required: true },
    items: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    models: { type: Array, default: () => [] },
});

function switchType(slug) {
    router.get(route('trash.index'), { type: slug }, { preserveScroll: true });
}

function restore(id) {
    if (!confirm(`Restore ${props.label} #${id}?`)) return;
    router.post(route('trash.restore', { type: props.type, id }), {}, { preserveScroll: true });
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Trash" />

    <AppLayout>
        <PageHeader title="Trash" description="Restore item yang sudah ke-delete (soft delete)." :icon="Trash2" />

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="m in models" :key="m.slug" type="button"
                :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs ring-1', m.slug === type ? 'bg-primary text-primary-foreground ring-primary' : 'bg-card ring-border hover:bg-muted/30']"
                @click="switchType(m.slug)">
                {{ m.label }}
                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-background/30">{{ counts[m.slug] ?? 0 }}</span>
            </button>
        </div>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">ID</TableHead>
                        <TableHead>Label</TableHead>
                        <TableHead>Deleted At</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="items.data.length === 0">
                        <TableCell colspan="4" class="text-center py-16 text-muted-foreground text-sm">Trash kosong.</TableCell>
                    </TableRow>
                    <TableRow v-for="item in items.data" :key="item.id">
                        <TableCell class="pl-4 font-mono text-xs">{{ item.id }}</TableCell>
                        <TableCell>{{ item.name ?? item.code ?? item.invoice_number ?? item.po_number ?? item.do_number ?? item.return_number ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ fmt(item.deleted_at) }}</TableCell>
                        <TableCell class="text-right pr-4">
                            <Button size="sm" variant="outline" class="h-7 px-2" @click="restore(item.id)">
                                <RotateCcw class="size-3.5" /> Restore
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div v-if="items.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="items" />
            </div>
        </section>
    </AppLayout>
</template>
