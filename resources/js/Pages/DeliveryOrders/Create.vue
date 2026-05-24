<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Truck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    openSalesOrders: { type: Array, required: true },
    selectedSo: { type: Object, default: null },
});

const so = ref(props.selectedSo);

const form = useForm({
    sales_order_id: props.selectedSo?.id ?? null,
    expected_delivery_date: '',
    notes: '',
    items: [], // [{so_item_id, qty_planned, ...display}]
});

async function loadSo(soId) {
    if (!soId) {
        so.value = null;
        form.items = [];
        return;
    }
    try {
        const res = await fetch(route('delivery-orders.so-details', soId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        so.value = data.so;
        // Auto-populate items dengan qty_planned = remaining
        form.items = (data.so?.items ?? [])
            .filter((i) => i.qty_remaining > 0)
            .map((i) => ({
                so_item_id: i.id,
                product_name: i.product_name,
                product_sku: i.product_sku,
                unit_name: i.unit_name,
                qty_ordered: i.qty,
                qty_delivered_already: i.qty_delivered,
                qty_remaining: i.qty_remaining,
                qty_planned: i.qty_remaining,
                is_bonus: i.is_bonus,
            }));
        if (data.so?.eta_date) {
            form.expected_delivery_date = data.so.eta_date;
        }
    } catch {
        so.value = null;
        form.items = [];
    }
}

function onSoChange(soId) {
    form.sales_order_id = soId ? Number(soId) : null;
    loadSo(form.sales_order_id);
}

watch(
    () => form.sales_order_id,
    (val) => {
        if (val && !so.value) loadSo(val);
    },
    { immediate: true },
);

function submit() {
    form
        .transform((data) => ({
            sales_order_id: data.sales_order_id,
            expected_delivery_date: data.expected_delivery_date || null,
            notes: data.notes,
            items: data.items
                .filter((r) => Number(r.qty_planned) > 0)
                .map((r) => ({
                    so_item_id: r.so_item_id,
                    qty_planned: Number(r.qty_planned),
                })),
        }))
        .post(route('delivery-orders.store'));
}

const hasItems = computed(() => form.items.some((r) => Number(r.qty_planned) > 0));
</script>

<template>
    <Head title="Buat Surat Jalan" />

    <AppLayout>
        <PageHeader title="Buat Surat Jalan" description="Pilih SO approved/partial, atur qty per item. Reservasi auto-distribusi per batch." :icon="Truck">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('delivery-orders.index')">
                        <ArrowLeft class="size-4" /> Daftar DO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <!-- Header -->
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-3">Header</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Sales Order *</Label>
                        <Select
                            :model-value="form.sales_order_id ? String(form.sales_order_id) : ''"
                            @update:model-value="onSoChange"
                        >
                            <SelectTrigger class="h-9">
                                <SelectValue placeholder="Pilih SO yang approved / partially_delivered" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in openSalesOrders" :key="s.id" :value="String(s.id)">
                                    {{ s.so_number }} · {{ s.customer?.name }}
                                    <span class="text-[10px] text-muted-foreground ml-1">({{ s.status }})</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Estimasi Tiba</Label>
                        <Input v-model="form.expected_delivery_date" type="date" class="h-9" />
                    </div>
                    <div v-if="so" class="sm:col-span-3 text-xs text-muted-foreground">
                        Tujuan: <strong>{{ so.customer?.name }}</strong>
                        <span v-if="so.customer?.address">— {{ so.customer.address }}</span>
                        <span v-if="so.customer?.city">, {{ so.customer.city }}</span>
                    </div>
                </div>
            </section>

            <!-- Items -->
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
                <header class="border-b border-border/70 px-5 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Items ({{ form.items.length }})</p>
                </header>
                <p v-if="form.errors.items" class="text-xs text-destructive px-5 pt-2">{{ form.errors.items }}</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                                <th class="text-left py-2 px-5">Produk</th>
                                <th class="text-left py-2 px-3">Unit</th>
                                <th class="text-right py-2 px-3">SO Order</th>
                                <th class="text-right py-2 px-3">Terkirim</th>
                                <th class="text-right py-2 px-3">Sisa</th>
                                <th class="text-right py-2 px-5 w-[15%]">Qty Plan *</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr v-if="form.items.length === 0">
                                <td colspan="6" class="py-8 text-center text-muted-foreground text-xs">
                                    {{ form.sales_order_id ? 'Tidak ada item tersisa untuk dikirim.' : 'Pilih SO dulu.' }}
                                </td>
                            </tr>
                            <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-muted/20">
                                <td class="py-2 px-5">
                                    <p class="font-medium">
                                        {{ row.product_name }}
                                        <span v-if="row.is_bonus" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                            BONUS
                                        </span>
                                    </p>
                                    <p class="text-[11px] text-muted-foreground font-mono">{{ row.product_sku }}</p>
                                </td>
                                <td class="py-2 px-3">{{ row.unit_name }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ row.qty_ordered }}</td>
                                <td class="py-2 px-3 text-right font-mono text-muted-foreground">{{ row.qty_delivered_already }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ row.qty_remaining }}</td>
                                <td class="py-2 px-5">
                                    <Input
                                        v-model="row.qty_planned"
                                        type="number"
                                        min="0"
                                        :max="row.qty_remaining"
                                        class="h-8 text-right font-mono"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Notes -->
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
                <Label class="text-xs font-medium">Catatan</Label>
                <Textarea v-model="form.notes" rows="2" class="mt-1" />
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline" size="default">
                    <Link :href="route('delivery-orders.index')">Batal</Link>
                </Button>
                <Button
                    type="submit"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing || !form.sales_order_id || !hasItems"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Draft' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
