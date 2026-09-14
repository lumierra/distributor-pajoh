<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackagePlus, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
    customers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    openInvoices: { type: Array, default: () => [] },
    salesUsers: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
});

const form = useForm({
    // Prefill dari "Sesuaikan" di halaman SO (customer & faktur terkait).
    customer_id: props.prefill?.customer_id ? String(props.prefill.customer_id) : '',
    sales_id: '',
    return_date: new Date().toISOString().slice(0, 10),
    invoice_id: props.prefill?.invoice_id ? String(props.prefill.invoice_id) : '',
    brand_tag: '',
    reason_code: '',
    reason_notes: '',
    notes: '',
    proof_photo: null,
    items: [
        { product_id: '', product_unit_id: '', qty_total: 1, qty_good: 0, qty_bs: 0, unit_price: 0, batch_id: '' },
    ],
});

const customerInvoices = computed(() =>
    props.openInvoices.filter((i) => String(i.customer_id) === String(form.customer_id)),
);

const productUnits = ref({});

async function loadProductUnits(productId, rowIdx) {
    if (!productId || productUnits.value[productId]) return;
    try {
        const res = await fetch(`/api/internal/products/${productId}/units`, { credentials: 'same-origin' });
        if (res.ok) {
            productUnits.value[productId] = await res.json();
            const firstUnit = productUnits.value[productId][0];
            if (firstUnit && !form.items[rowIdx].product_unit_id) {
                form.items[rowIdx].product_unit_id = firstUnit.id;
            }
        }
    } catch (e) {
        // noop, biarkan user pilih manual
    }
}

function addItem() {
    form.items.push({ product_id: '', product_unit_id: '', qty_total: 1, qty_good: 0, qty_bs: 0, unit_price: 0, batch_id: '' });
}

function removeItem(idx) {
    form.items.splice(idx, 1);
}

function submit() {
    form.post(route('customer-returns.store'), { forceFormData: true });
}
</script>

<template>
    <Head title="Buat Retur Customer" />

    <AppLayout>
        <PageHeader title="Buat Retur Customer" :icon="PackagePlus">
            <template #actions>
                <Button as-child variant="outline" size="default" class="rounded-full">
                    <Link :href="route('customer-returns.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form class="grid grid-cols-1 lg:grid-cols-3 gap-4" @submit.prevent="submit">
            <section class="lg:col-span-2 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Header</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Customer</Label>
                        <Select v-model="form.customer_id">
                            <SelectTrigger><SelectValue placeholder="Pilih customer" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                    {{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.customer_id" class="text-xs text-red-600 mt-1">{{ form.errors.customer_id }}</p>
                    </div>
                    <div>
                        <Label>Sales (opsional)</Label>
                        <Select v-model="form.sales_id">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Tgl Retur</Label>
                        <Input v-model="form.return_date" type="date" />
                    </div>
                    <div>
                        <Label>Invoice Terkait (opsional)</Label>
                        <Select v-model="form.invoice_id" :disabled="!form.customer_id">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="i in customerInvoices" :key="i.id" :value="String(i.id)">
                                    {{ i.invoice_number }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Brand Tag</Label>
                        <Input v-model="form.brand_tag" placeholder="MamaSuka, Mayora, dll" />
                    </div>
                    <div>
                        <Label>Reason</Label>
                        <Select v-model="form.reason_code">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="expired">Expired</SelectItem>
                                <SelectItem value="damaged">Damaged</SelectItem>
                                <SelectItem value="wrong_item">Wrong Item</SelectItem>
                                <SelectItem value="customer_request">Customer Request</SelectItem>
                                <SelectItem value="quality_issue">Quality Issue</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div>
                    <Label>Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
                <div>
                    <Label>Foto Bukti (opsional)</Label>
                    <Input type="file" accept="image/*" @input="form.proof_photo = $event.target.files[0]" />
                </div>
            </section>

            <aside class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 h-fit">
                <h3 class="text-sm font-semibold mb-2">Submit</h3>
                <p class="text-xs text-muted-foreground mb-3">Retur akan disimpan sebagai draft. Sortir BAIK/BS dilakukan setelahnya oleh operator.</p>
                <Button type="submit" :disabled="form.processing" class="w-full rounded-full bg-brand text-white hover:bg-brand-dark">Simpan Draft</Button>
            </aside>

            <section class="lg:col-span-3 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold">Item Retur</h3>
                    <Button type="button" size="sm" variant="outline" class="rounded-full" @click="addItem">
                        <Plus class="size-3.5" /> Tambah Item
                    </Button>
                </div>
                <div class="space-y-2">
                    <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-2 items-end p-2 rounded-2xl ring-1 ring-foreground/8">
                        <div class="col-span-3">
                            <Label class="text-[11px]">Produk</Label>
                            <Select v-model="item.product_id" @update:model-value="loadProductUnits($event, idx)">
                                <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="col-span-2">
                            <Label class="text-[11px]">Unit</Label>
                            <Select v-model="item.product_unit_id">
                                <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="u in (productUnits[item.product_id] || [])" :key="u.id" :value="String(u.id)">
                                        {{ u.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="col-span-1">
                            <Label class="text-[11px]">Qty</Label>
                            <Input v-model.number="item.qty_total" type="number" min="1" />
                        </div>
                        <div class="col-span-2">
                            <Label class="text-[11px]">Unit Price</Label>
                            <Input v-model.number="item.unit_price" type="number" min="0" step="0.01" />
                        </div>
                        <div class="col-span-3">
                            <Label class="text-[11px]">Batch ID (opsional)</Label>
                            <Input v-model="item.batch_id" placeholder="kosongkan untuk pool" />
                        </div>
                        <div class="col-span-1 flex justify-end">
                            <Button type="button" variant="ghost" size="sm" @click="removeItem(idx)" :disabled="form.items.length === 1">
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
