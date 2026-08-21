<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle2, Pencil, SlidersHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import AdjustmentStatusBadge from '@/Components/Inventory/AdjustmentStatusBadge.vue';
import { DIRECTION_LABELS, REASON_LABELS } from '@/Components/Inventory/adjustmentMeta';
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
    adjustment: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const postForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });

async function doPost() {
    if (!(await confirm({
        title: `Posting ${props.adjustment.adjustment_number}?`,
        description: 'Stok akan berubah sesuai penyesuaian. Aksi ini final & tidak bisa diundo.',
    }))) return;
    postForm.post(route('adjustments.post', props.adjustment.id), { preserveScroll: true });
}

function doCancel() {
    cancelForm.post(route('adjustments.cancel', props.adjustment.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="adjustment.adjustment_number" />

    <AppLayout>
        <PageHeader
            :title="adjustment.adjustment_number"
            :description="`${REASON_LABELS[adjustment.reason_category] ?? adjustment.reason_category} · ${formatDate(adjustment.adjustment_date)}`"
            :icon="SlidersHorizontal"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('adjustments.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Adjustment
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('adjustments.edit', adjustment.id)">
                        <Pencil class="size-4" /> Edit
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
        <section class="mb-4">
            <div class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0">
                    <SlidersHorizontal class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-dark/70">
                        FY {{ adjustment.fiscal_year }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-brand-dark truncate font-mono">{{ adjustment.adjustment_number }}</h2>
                    <p class="text-xs text-brand-dark/70 mt-1">
                        Alasan: <strong>{{ REASON_LABELS[adjustment.reason_category] ?? adjustment.reason_category }}</strong>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <AdjustmentStatusBadge :status="adjustment.status" />
                    <p class="text-xs text-brand-dark/70">{{ formatDate(adjustment.adjustment_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3 border-b border-foreground/5">
                <h3 class="text-sm font-semibold">Items ({{ adjustment.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5 font-semibold">Produk</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Batch</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Stok Saat Input</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Arah</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Qty</th>
                            <th class="text-right py-2.5 px-5 font-semibold">Cost/Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in adjustment.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code_snapshot }}</td>
                            <td class="py-2.5 px-3 text-right font-mono text-xs text-muted-foreground">{{ i.system_qty_snapshot }}</td>
                            <td class="py-2.5 px-3">
                                <span
                                    :class="[
                                        'inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium',
                                        i.direction === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
                                    ]"
                                >
                                    {{ DIRECTION_LABELS[i.direction] ?? i.direction }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono" :class="i.direction === 'in' ? 'text-emerald-700' : 'text-amber-700'">
                                {{ i.direction === 'in' ? '+' : '−' }}{{ i.qty }}
                                <span v-if="i.product_unit_name_snapshot" class="text-[11px] font-sans opacity-70">{{ i.product_unit_name_snapshot }}</span>
                                <span v-if="i.qty_base && Number(i.qty_base) !== Number(i.qty)" class="block text-[10px] text-muted-foreground font-mono">
                                    = {{ Number(i.qty_base).toLocaleString('id-ID') }} base
                                </span>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ formatRp(i.cost_price) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="adjustment.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ adjustment.notes }}</p>
        </div>

        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDate(adjustment.created_at) }}</strong>{{ adjustment.creator ? ` · oleh ${adjustment.creator.name}` : '' }}</li>
                <li v-if="adjustment.posted_at" class="text-emerald-700">Diposting <strong>{{ formatDate(adjustment.posted_at) }}</strong>{{ adjustment.poster ? ` · oleh ${adjustment.poster.name}` : '' }}</li>
                <li v-if="adjustment.cancelled_at" class="text-red-700">
                    Dibatalkan <strong>{{ formatDate(adjustment.cancelled_at) }}</strong>{{ adjustment.canceller ? ` · oleh ${adjustment.canceller.name}` : '' }}
                    <span v-if="adjustment.cancel_reason"> — "{{ adjustment.cancel_reason }}"</span>
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
                            <DialogTitle class="text-base font-semibold tracking-tight">Batalkan Adjustment</DialogTitle>
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
