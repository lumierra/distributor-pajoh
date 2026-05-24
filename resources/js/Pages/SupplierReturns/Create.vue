<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackageOpen, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
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
    suppliers: { type: Array, default: () => [] },
    selectedSupplierId: { type: [String, Number, null], default: null },
    sources: {
        type: Object,
        default: () => ({ grn_damaged: [], customer_return_bs: [], stock: [] }),
    },
});

const form = useForm({
    supplier_id: props.selectedSupplierId ? String(props.selectedSupplierId) : '',
    return_date: new Date().toISOString().slice(0, 10),
    reason_code: 'damaged',
    reason_notes: '',
    notes: '',
    proof_photo: null,
    items: [],
});

watch(
    () => form.supplier_id,
    (newVal) => {
        if (newVal && String(newVal) !== String(props.selectedSupplierId)) {
            router.get(route('supplier-returns.create'), { supplier_id: newVal }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }
    },
);

const allSources = computed(() => [
    ...(props.sources.grn_damaged || []).map((s) => ({ ...s, _label: `GRN #${s.grn_item_id} — ${s.product_name} (${s.batch_code ?? 'no batch'}) — avail ${s.available_qty}` })),
    ...(props.sources.customer_return_bs || []).map((s) => ({ ...s, _label: `CR BS — ${s.customer_return_number} — ${s.product_name} — avail ${s.available_qty}` })),
    ...(props.sources.stock || []).map((s) => ({ ...s, _label: `Stock — ${s.product_name} (${s.batch_code ?? '—'}) — avail ${s.available_qty}` })),
]);

function pickSource(src) {
    form.items.push({
        source_type: src.source_type,
        source_id: src.source_id,
        grn_item_id: src.grn_item_id ?? null,
        customer_return_item_id: src.customer_return_item_id ?? null,
        product_id: String(src.product_id),
        product_unit_id: String(src.product_unit_id),
        product_name: src.product_name,
        batch_id: src.batch_id ? String(src.batch_id) : '',
        batch_code: src.batch_code ?? null,
        qty: src.available_qty,
        cost_price: src.cost_price ?? 0,
        available_qty: src.available_qty,
    });
}

function removeItem(idx) {
    form.items.splice(idx, 1);
}

function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.map((i) => ({
            source_type: i.source_type,
            source_id: i.source_id,
            grn_item_id: i.grn_item_id,
            customer_return_item_id: i.customer_return_item_id,
            product_id: Number(i.product_id),
            product_unit_id: Number(i.product_unit_id),
            batch_id: i.batch_id ? Number(i.batch_id) : null,
            qty: Number(i.qty),
            cost_price: Number(i.cost_price),
        })),
    })).post(route('supplier-returns.store'), { forceFormData: true });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Buat Retur Supplier" />

    <AppLayout>
        <PageHeader title="Buat Retur Supplier" :icon="PackageOpen">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('supplier-returns.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form class="grid grid-cols-1 lg:grid-cols-3 gap-4" @submit.prevent="submit">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Header</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Supplier</Label>
                        <Select v-model="form.supplier_id">
                            <SelectTrigger><SelectValue placeholder="Pilih supplier" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.supplier_id" class="text-xs text-red-600 mt-1">{{ form.errors.supplier_id }}</p>
                    </div>
                    <div>
                        <Label>Tgl Retur</Label>
                        <Input v-model="form.return_date" type="date" />
                    </div>
                    <div class="col-span-2">
                        <Label>Reason</Label>
                        <Select v-model="form.reason_code">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="damaged">Damaged</SelectItem>
                                <SelectItem value="expired">Expired</SelectItem>
                                <SelectItem value="quality">Quality</SelectItem>
                                <SelectItem value="wrong_item">Wrong Item</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div>
                    <Label>Reason Notes</Label>
                    <Textarea v-model="form.reason_notes" rows="2" />
                </div>
                <div>
                    <Label>Notes</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
                <div>
                    <Label>Foto Bukti</Label>
                    <Input type="file" accept="image/*" @input="form.proof_photo = $event.target.files[0]" />
                </div>
            </section>

            <aside class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 h-fit">
                <h3 class="text-sm font-semibold mb-2">Submit</h3>
                <Button type="submit" :disabled="form.processing || form.items.length === 0" class="w-full">
                    Simpan Draft
                </Button>
                <p v-if="form.items.length === 0" class="text-xs text-muted-foreground mt-2">Pilih minimal 1 item dari sources di bawah.</p>
            </aside>

            <section class="lg:col-span-3 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Sources Tersedia</h3>
                <p v-if="!form.supplier_id" class="text-xs text-muted-foreground">Pilih supplier dulu untuk melihat sources.</p>
                <div v-else-if="allSources.length === 0" class="text-xs text-muted-foreground">Tidak ada item yang available untuk supplier ini.</div>
                <ul v-else class="space-y-1 text-sm max-h-64 overflow-y-auto">
                    <li v-for="(src, idx) in allSources" :key="idx" class="flex items-center justify-between p-2 rounded ring-1 ring-border/60 hover:bg-muted/30">
                        <div>
                            <span class="text-[10px] font-semibold uppercase text-muted-foreground mr-2">{{ src.source_type }}</span>
                            <span>{{ src._label }}</span>
                            <span v-if="src.cost_price" class="text-xs text-muted-foreground ml-2">cost: {{ fmtRp(src.cost_price) }}</span>
                        </div>
                        <Button type="button" size="sm" variant="outline" @click="pickSource(src)">
                            <Plus class="size-3.5" /> Pilih
                        </Button>
                    </li>
                </ul>
            </section>

            <section v-if="form.items.length > 0" class="lg:col-span-3 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Items Retur</h3>
                <div class="space-y-2">
                    <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-2 items-end p-2 rounded ring-1 ring-border/60">
                        <div class="col-span-4">
                            <Label class="text-[10px]">Source</Label>
                            <p class="text-xs">{{ item.source_type }}</p>
                            <p class="font-medium text-sm">{{ item.product_name }}</p>
                        </div>
                        <div class="col-span-2">
                            <Label class="text-[10px]">Batch</Label>
                            <p class="font-mono text-xs">{{ item.batch_code ?? '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <Label class="text-[10px]">Qty (max {{ item.available_qty }})</Label>
                            <Input v-model.number="item.qty" type="number" :min="1" :max="item.available_qty" />
                        </div>
                        <div class="col-span-3">
                            <Label class="text-[10px]">Cost Price</Label>
                            <Input v-model.number="item.cost_price" type="number" min="0" step="0.01" />
                        </div>
                        <div class="col-span-1 flex justify-end">
                            <Button type="button" variant="ghost" size="sm" @click="removeItem(idx)">
                                <Trash2 class="size-4 text-red-600" />
                            </Button>
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.items" class="text-xs text-red-600 mt-2">{{ form.errors.items }}</p>
            </section>
        </form>
    </AppLayout>
</template>
