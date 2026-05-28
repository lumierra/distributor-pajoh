<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Save, Search, ShieldAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    open: { type: Boolean, default: false },
    customer: { type: Object, required: true },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'saved']);

const loading = ref(false);
const rows = ref([]); // [{ supplier_id, supplier_code, supplier_name, credit_limit, outstanding, available }]
const search = ref('');

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return rows.value;
    return rows.value.filter(
        (r) => (r.supplier_name ?? '').toLowerCase().includes(q)
            || (r.supplier_code ?? '').toLowerCase().includes(q),
    );
});

async function load() {
    loading.value = true;
    try {
        const res = await fetch(
            route('customers.credit-limits.index', props.customer.id),
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        if (!res.ok) throw new Error('Failed to load');
        const data = await res.json();
        rows.value = (data.rows ?? []).map((r) => ({
            ...r,
            credit_limit: r.credit_limit ?? null,
        }));
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            search.value = '';
            load();
        }
    },
);

const form = useForm({ rows: [] });

function save() {
    form.rows = rows.value.map((r) => ({
        supplier_id: r.supplier_id,
        credit_limit:
            r.credit_limit === '' || r.credit_limit === null
                ? null
                : Number(r.credit_limit),
    }));
    form.put(route('customers.credit-limits.sync', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('update:open', false);
        },
    });
}

function fmtRp(v) {
    if (v === null || v === undefined || v === '') return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function availableLabel(r) {
    if (r.credit_limit === null || r.credit_limit === '' || Number(r.credit_limit) <= 0) {
        return '—';
    }
    const available = Math.max(0, Number(r.credit_limit) - Number(r.outstanding ?? 0));
    return fmtRp(available);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[760px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 ring-1 ring-amber-200">
                        <ShieldAlert class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Credit Limit per Supplier — {{ customer.name }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Atur limit piutang max customer per supplier. Kosong / 0 = tanpa limit.
                            Sales akan di-block submit SO yang menyebabkan outstanding lewat limit.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="px-5 py-3 border-b border-border/70 bg-muted/30 flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Cari supplier (kode / nama)…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
            </div>

            <div class="max-h-[55vh] overflow-y-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                            <TableHead class="pl-4">Supplier</TableHead>
                            <TableHead class="text-right">Outstanding</TableHead>
                            <TableHead class="text-right">Limit (Rp)</TableHead>
                            <TableHead class="text-right pr-4">Sisa</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="loading">
                            <TableCell colspan="4" class="text-center py-8 text-muted-foreground">
                                <Loader2 class="size-4 animate-spin inline-block mr-2" />
                                Memuat…
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="filteredRows.length === 0">
                            <TableCell colspan="4" class="text-center py-8 text-muted-foreground">
                                Tidak ada supplier.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="r in filteredRows"
                            :key="r.supplier_id"
                            :class="[
                                'hover:bg-muted/30 transition-colors',
                                Number(r.credit_limit ?? 0) > 0 ? 'bg-emerald-50/30' : '',
                            ]"
                        >
                            <TableCell class="pl-4 py-2.5">
                                <p class="font-medium">{{ r.supplier_name }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ r.supplier_code }}</p>
                            </TableCell>
                            <TableCell class="text-right font-mono text-xs">
                                {{ fmtRp(r.outstanding) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end">
                                    <Input
                                        v-model="r.credit_limit"
                                        type="number"
                                        min="0"
                                        step="100000"
                                        placeholder="tanpa limit"
                                        :disabled="!canUpdate"
                                        class="h-8 w-40 text-right font-mono text-xs"
                                    />
                                </div>
                            </TableCell>
                            <TableCell class="text-right pr-4 font-mono text-xs">
                                {{ availableLabel(r) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">
                    Tutup
                </Button>
                <Button
                    v-if="canUpdate"
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing || loading"
                    @click="save"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
