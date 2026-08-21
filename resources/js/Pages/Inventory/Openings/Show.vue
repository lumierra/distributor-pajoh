<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CheckCircle2, PackagePlus, Pencil } from '@lucide/vue';
import { ref } from 'vue';
import AdjustmentStatusBadge from '@/Components/Inventory/AdjustmentStatusBadge.vue';
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
    opening: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const postForm = useForm({});
const cancelForm = useForm({ cancel_reason: '' });

async function doPost() {
    if (!(await confirm({
        title: `Posting ${props.opening.opening_number}?`,
        description: 'Stok akan masuk sesuai daftar item. Aksi ini final & tidak bisa diundo.',
    }))) return;
    postForm.post(route('openings.post', props.opening.id), { preserveScroll: true });
}

function doCancel() {
    cancelForm.post(route('openings.cancel', props.opening.id), {
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
    <Head :title="opening.opening_number" />

    <AppLayout>
        <PageHeader
            :title="opening.opening_number"
            :description="`Stok Awal · ${formatDate(opening.opening_date)}`"
            :icon="PackagePlus"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('openings.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Stok Awal
                    </Link>
                </Button>
                <Button v-if="canEdit" as-child size="default" variant="outline" class="rounded-full">
                    <Link :href="route('openings.edit', opening.id)">
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
                    <PackagePlus class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-dark/70">
                        FY {{ opening.fiscal_year }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-brand-dark truncate font-mono">{{ opening.opening_number }}</h2>
                    <p class="text-xs text-brand-dark/70 mt-1">Saldo awal stok (opening balance)</p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <AdjustmentStatusBadge :status="opening.status" />
                    <p class="text-xs text-brand-dark/70">{{ formatDate(opening.opening_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Items -->
        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
            <header class="px-5 py-3 border-b border-foreground/5">
                <h3 class="text-sm font-semibold">Items ({{ opening.items?.length ?? 0 }})</h3>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                            <th class="text-left py-2.5 px-5 font-semibold">Produk</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Batch</th>
                            <th class="text-left py-2.5 px-3 font-semibold">Kadaluarsa</th>
                            <th class="text-right py-2.5 px-3 font-semibold">Qty</th>
                            <th class="text-right py-2.5 px-5 font-semibold">Harga Modal/Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-foreground/5">
                        <tr v-for="i in opening.items" :key="i.id" class="hover:bg-foreground/2.5 transition-colors">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">{{ i.product_name_snapshot }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code }}</td>
                            <td class="py-2.5 px-3 text-xs text-muted-foreground">{{ i.expired_date ? formatDate(i.expired_date) : '—' }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">
                                <p v-if="Number(i.qty) > 0" class="text-emerald-700">
                                    +{{ i.qty }} <span class="text-muted-foreground font-sans text-xs">{{ i.product_unit_name_snapshot }}</span>
                                    <span v-if="i.qty_base && i.qty_base !== i.qty" class="text-[11px] text-muted-foreground"> = {{ Number(i.qty_base).toLocaleString('id-ID') }} base</span>
                                </p>
                                <p v-if="Number(i.qty_bonus) > 0" class="text-[11px] text-emerald-600">
                                    +{{ i.qty_bonus }} {{ i.product_unit_name_snapshot }} bonus
                                    <span v-if="i.qty_bonus_base && i.qty_bonus_base !== i.qty_bonus"> = {{ Number(i.qty_bonus_base).toLocaleString('id-ID') }} base</span>
                                </p>
                            </td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ formatRp(i.cost_price) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="opening.notes" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
            <p class="text-sm whitespace-pre-line">{{ opening.notes }}</p>
        </div>

        <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDate(opening.created_at) }}</strong>{{ opening.creator ? ` · oleh ${opening.creator.name}` : '' }}</li>
                <li v-if="opening.posted_at" class="text-emerald-700">Diposting <strong>{{ formatDate(opening.posted_at) }}</strong>{{ opening.poster ? ` · oleh ${opening.poster.name}` : '' }}</li>
                <li v-if="opening.cancelled_at" class="text-red-700">
                    Dibatalkan <strong>{{ formatDate(opening.cancelled_at) }}</strong>{{ opening.canceller ? ` · oleh ${opening.canceller.name}` : '' }}
                    <span v-if="opening.cancel_reason"> — "{{ opening.cancel_reason }}"</span>
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
                            <DialogTitle class="text-base font-semibold tracking-tight">Batalkan Stok Awal</DialogTitle>
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
