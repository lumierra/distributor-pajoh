<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle2, ClipboardCheck, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import OpnameStatusBadge from '@/Components/Inventory/OpnameStatusBadge.vue';
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
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    opname: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const postForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });

const posted = computed(() => props.opname.status === 'posted');

async function doPost() {
    if (!(await confirm({
        title: `Posting ${props.opname.opname_number}?`,
        description: 'Stok sistem akan disesuaikan ke hasil hitung fisik. Aksi ini final & tidak bisa diundo.',
    }))) return;
    postForm.post(route('opnames.post', props.opname.id), { preserveScroll: true });
}

function doCancel() {
    cancelForm.post(route('opnames.cancel', props.opname.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Variance untuk tampilan: kalau sudah posted pakai kolom variance final,
// kalau draft hitung dari counted − system_qty snapshot (preview).
function itemVariance(i) {
    if (i.counted_qty === null || i.counted_qty === undefined) return null;
    if (posted.value) return i.variance;
    return Number(i.counted_qty) - Number(i.system_qty_snapshot);
}

const summary = computed(() => {
    let counted = 0;
    let plus = 0;
    let minus = 0;
    for (const i of props.opname.items ?? []) {
        if (i.counted_qty === null || i.counted_qty === undefined) continue;
        counted++;
        const v = itemVariance(i);
        if (v > 0) plus += v;
        else if (v < 0) minus += Math.abs(v);
    }
    return { counted, total: (props.opname.items ?? []).length, plus, minus };
});
</script>

<template>
    <Head :title="opname.opname_number" />

    <AppLayout>
        <PageHeader
            :title="opname.opname_number"
            :description="`Opname · ${formatDate(opname.opname_date)}`"
            :icon="ClipboardCheck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('opnames.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Opname
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('opnames.edit', opname.id)">
                        <Pencil class="size-4" /> Isi / Edit Hitung
                    </Link>
                </Button>
                <Button
                    v-if="canPost"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="doPost"
                >
                    <CheckCircle2 class="size-4" /> Posting
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" class="rounded-full" @click="cancelOpen = true">
                    <Ban class="size-4" /> Batalkan
                </Button>
            </template>
        </PageHeader>

        <!-- Summary -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5">
                <p class="text-lg font-semibold tracking-tight text-brand-dark">{{ summary.counted }} / {{ summary.total }}</p>
                <p class="text-[12px] text-brand-dark/70 mt-0.5">Dihitung</p>
            </div>
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 shadow-sm px-4 py-3.5">
                <p class="text-lg font-semibold tracking-tight text-emerald-700">+{{ summary.plus.toLocaleString('id-ID') }}</p>
                <p class="text-[12px] text-emerald-700/80 mt-0.5">Total Lebih (fisik &gt; sistem)</p>
            </div>
            <div class="rounded-2xl bg-red-50 ring-1 ring-red-200 shadow-sm px-4 py-3.5">
                <p class="text-lg font-semibold tracking-tight text-red-600">−{{ summary.minus.toLocaleString('id-ID') }}</p>
                <p class="text-[12px] text-red-600/80 mt-0.5">Total Kurang (fisik &lt; sistem)</p>
            </div>
            <div class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5 flex items-center justify-between">
                <div>
                    <div class="mb-1"><OpnameStatusBadge :status="opname.status" /></div>
                    <p class="text-[12px] text-brand-dark/70">{{ formatDate(opname.opname_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3 border-b border-foreground/5">
                <h3 class="text-sm font-semibold">Item ({{ opname.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5 font-semibold">Produk</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Batch</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Stok Sistem</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Fisik</th>
                            <th class="text-right py-2.5 px-5 font-semibold">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in opname.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-muted-foreground">{{ i.system_qty_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                <span v-if="i.counted_qty === null || i.counted_qty === undefined" class="text-muted-foreground">belum</span>
                                <span v-else>{{ i.counted_qty }}</span>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">
                                <span v-if="itemVariance(i) === null" class="text-muted-foreground">—</span>
                                <span v-else-if="itemVariance(i) === 0" class="text-muted-foreground">0</span>
                                <span v-else :class="itemVariance(i) > 0 ? 'text-emerald-700' : 'text-red-600'">
                                    {{ itemVariance(i) > 0 ? '+' : '' }}{{ itemVariance(i) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="opname.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ opname.notes }}</p>
        </div>

        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDate(opname.created_at) }}</strong>{{ opname.creator ? ` · oleh ${opname.creator.name}` : '' }}</li>
                <li v-if="opname.posted_at" class="text-emerald-700">Diposting <strong>{{ formatDate(opname.posted_at) }}</strong>{{ opname.poster ? ` · oleh ${opname.poster.name}` : '' }}</li>
                <li v-if="opname.cancelled_at" class="text-red-700">
                    Dibatalkan <strong>{{ formatDate(opname.cancelled_at) }}</strong>{{ opname.canceller ? ` · oleh ${opname.canceller.name}` : '' }}
                    <span v-if="opname.cancel_reason"> — "{{ opname.cancel_reason }}"</span>
                </li>
            </ul>
        </div>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <Ban class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Batalkan Opname</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Hanya draft yang bisa dibatalkan. Aksi ini final.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-6 pb-2" @submit.prevent="doCancel">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="cancelForm.cancel_reason" rows="3" required class="mt-1.5 rounded-xl" />
                    <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-destructive mt-1">{{ cancelForm.errors.cancel_reason }}</p>
                </form>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="cancelOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" class="rounded-full" :disabled="cancelForm.processing" @click="doCancel">Batalkan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
