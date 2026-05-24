<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CheckCircle2,
    ClipboardList,
    FileText,
    Loader2,
    Package,
    PackageCheck,
    Save,
    Truck,
    Upload,
    X,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import DoStatusBadge from '@/Components/DeliveryOrders/DoStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    deliveryOrder: { type: Object, required: true },
    drivers: { type: Array, required: true },
    vehicles: { type: Array, required: true },
    canEdit: { type: Boolean, default: false },
    canStartPicking: { type: Boolean, default: false },
    canConfirmPick: { type: Boolean, default: false },
    canMarkPacked: { type: Boolean, default: false },
    canStartDelivery: { type: Boolean, default: false },
    canMarkDelivered: { type: Boolean, default: false },
    canCancel: { type: Boolean, default: false },
});

const packedOpen = ref(false);
const deliveredOpen = ref(false);
const cancelOpen = ref(false);

// Picking inline state
const picks = reactive(
    Object.fromEntries(
        (props.deliveryOrder.items ?? []).map((i) => [i.id, i.qty_picked]),
    ),
);

const startForm = useForm({});
const pickForm = useForm({});
const packedForm = useForm({ driver_id: null, vehicle_id: null });
const startDeliveryForm = useForm({});
const deliveredForm = useForm({
    receiver_name: '',
    receiver_notes: '',
    latitude: null,
    longitude: null,
    proof_photo: null,
    digital_signature: null,
    item_quantities: (props.deliveryOrder.items ?? []).map((i) => ({
        item_id: i.id,
        qty_delivered: i.qty_picked,
        qty_returned: 0,
    })),
});
const cancelForm = useForm({ cancel_reason: '' });

function doStartPicking() {
    if (!window.confirm('Mulai picking?')) return;
    startForm.post(route('delivery-orders.start-picking', props.deliveryOrder.id), { preserveScroll: true });
}

function doConfirmPicks() {
    const payload = Object.entries(picks).map(([itemId, qty]) => ({
        item_id: Number(itemId),
        qty_picked: Number(qty) || 0,
    }));
    pickForm.transform(() => ({ picks: payload }))
        .post(route('delivery-orders.confirm-picks', props.deliveryOrder.id), { preserveScroll: true });
}

function doMarkPacked() {
    packedForm.post(route('delivery-orders.mark-packed', props.deliveryOrder.id), {
        preserveScroll: true,
        onSuccess: () => (packedOpen.value = false),
    });
}

function doStartDelivery() {
    if (!window.confirm('Mulai delivery? Driver dianggap berangkat sekarang.')) return;
    startDeliveryForm.post(route('delivery-orders.start-delivery', props.deliveryOrder.id), { preserveScroll: true });
}

function onProofPhotoChange(e) {
    deliveredForm.proof_photo = e.target.files?.[0] ?? null;
}
function onSignatureChange(e) {
    deliveredForm.digital_signature = e.target.files?.[0] ?? null;
}

function doMarkDelivered() {
    deliveredForm
        .transform((data) => {
            const out = {
                receiver_name: data.receiver_name,
                receiver_notes: data.receiver_notes,
                latitude: data.latitude,
                longitude: data.longitude,
                proof_photo: data.proof_photo,
                digital_signature: data.digital_signature,
            };
            data.item_quantities.forEach((row, i) => {
                out[`item_quantities[${i}][item_id]`] = row.item_id;
                out[`item_quantities[${i}][qty_delivered]`] = row.qty_delivered;
                out[`item_quantities[${i}][qty_returned]`] = row.qty_returned;
            });
            return out;
        })
        .post(route('delivery-orders.mark-delivered', props.deliveryOrder.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => (deliveredOpen.value = false),
        });
}

function doCancel() {
    cancelForm.post(route('delivery-orders.cancel', props.deliveryOrder.id), {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
    });
}

function captureGps() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            deliveredForm.latitude = pos.coords.latitude;
            deliveredForm.longitude = pos.coords.longitude;
        },
        null,
        { enableHighAccuracy: true, timeout: 5000 },
    );
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function driverLicenseStatus(d) {
    if (!d.license_expired_date) return null;
    const days = Math.ceil((new Date(d.license_expired_date) - new Date()) / (1000 * 60 * 60 * 24));
    if (days < 0) return 'expired';
    if (days <= 30) return 'expiring';
    return null;
}
</script>

<template>
    <Head :title="`DO ${deliveryOrder.do_number}`" />

    <AppLayout>
        <PageHeader
            :title="`DO ${deliveryOrder.do_number}`"
            :description="`SO: ${deliveryOrder.sales_order?.so_number ?? '—'} · ${deliveryOrder.customer?.name ?? '—'}`"
            :icon="Truck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('delivery-orders.index')">
                        <ArrowLeft class="size-4" /> Daftar DO
                    </Link>
                </Button>
                <Button v-if="canStartPicking" size="default" variant="secondary" @click="doStartPicking">
                    <ClipboardList class="size-4" /> Start Picking
                </Button>
                <Button v-if="canMarkPacked" size="default" variant="secondary" @click="packedOpen = true">
                    <Package class="size-4" /> Mark Packed
                </Button>
                <Button v-if="canStartDelivery" size="default" variant="secondary" @click="doStartDelivery">
                    <Truck class="size-4" /> Start Delivery
                </Button>
                <Button v-if="canMarkDelivered" size="default" variant="secondary" @click="deliveredOpen = true">
                    <PackageCheck class="size-4" /> Mark Delivered
                </Button>
                <Button v-if="deliveryOrder.pdf_path" as-child size="default" variant="outline">
                    <a :href="route('delivery-orders.pdf', deliveryOrder.id)" target="_blank" rel="noopener">
                        <FileText class="size-4" /> Surat Jalan
                    </a>
                </Button>
                <Button v-if="canCancel" size="default" variant="outline" @click="cancelOpen = true">
                    <Ban class="size-4" /> Cancel
                </Button>
            </template>
        </PageHeader>

        <!-- Status banner -->
        <section v-if="deliveryOrder.has_partial_return" class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-3 mb-4 flex items-start gap-3 text-sm">
            <AlertTriangle class="size-5 text-amber-700 mt-0.5 shrink-0" />
            <p class="text-amber-900"><strong>Partial Return.</strong> Beberapa item ditolak customer saat antar.</p>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Truck class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        FY {{ deliveryOrder.fiscal_year }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">{{ deliveryOrder.do_number }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        SO: <Link :href="route('sales-orders.show', deliveryOrder.sales_order_id)" class="font-mono hover:text-primary">{{ deliveryOrder.sales_order?.so_number }}</Link>
                        · Customer: <strong>{{ deliveryOrder.customer?.name }}</strong>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <DoStatusBadge :status="deliveryOrder.status" />
                    <p class="text-xs text-muted-foreground">{{ formatDate(deliveryOrder.do_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Detail header -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Detail</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl DO</dt>
                        <dd class="mt-0.5">{{ formatDate(deliveryOrder.do_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Estimasi Tiba</dt>
                        <dd class="mt-0.5">{{ formatDate(deliveryOrder.expected_delivery_date) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Tujuan</h3>
                </header>
                <dl class="px-5 py-3 text-sm">
                    <p class="font-medium">{{ deliveryOrder.customer?.name }}</p>
                    <p class="text-xs text-muted-foreground font-mono">{{ deliveryOrder.customer?.code }}</p>
                    <p v-if="deliveryOrder.customer?.phone" class="text-xs">{{ deliveryOrder.customer.phone }}</p>
                    <p v-if="deliveryOrder.customer?.address" class="text-xs whitespace-pre-line">{{ deliveryOrder.customer.address }}</p>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Armada</h3>
                </header>
                <dl class="px-5 py-3 text-sm space-y-1">
                    <p v-if="deliveryOrder.driver">
                        Driver: <strong>{{ deliveryOrder.driver.name }}</strong>
                        <span v-if="deliveryOrder.driver.license_no" class="text-xs text-muted-foreground font-mono">SIM {{ deliveryOrder.driver.license_no }}</span>
                    </p>
                    <p v-if="deliveryOrder.vehicle">
                        Vehicle: <strong class="font-mono">{{ deliveryOrder.vehicle.plate_number }}</strong>
                        <span class="text-xs text-muted-foreground">({{ deliveryOrder.vehicle.type }})</span>
                    </p>
                    <p v-if="!deliveryOrder.driver && !deliveryOrder.vehicle" class="text-muted-foreground italic">
                        Belum di-assign (saat mark packed).
                    </p>
                </dl>
            </div>
        </div>

        <!-- Items + picking inline -->
        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Items ({{ deliveryOrder.items?.length ?? 0 }})</h3>
                <Button v-if="canConfirmPick" type="button" size="sm" variant="secondary" @click="doConfirmPicks">
                    <Save class="size-3.5" /> Simpan Qty Picked
                </Button>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border/70">
                            <th class="text-left py-2.5 px-5">Produk · Unit</th>
                            <th class="text-left py-2.5 px-3">Batch</th>
                            <th class="text-left py-2.5 px-3">Expired</th>
                            <th class="text-right py-2.5 px-3">Plan</th>
                            <th class="text-right py-2.5 px-3">Picked</th>
                            <th class="text-right py-2.5 px-3">Delivered</th>
                            <th class="text-right py-2.5 px-5">Returned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-for="i in deliveryOrder.items" :key="i.id" class="hover:bg-muted/20">
                            <td class="py-2.5 px-5">
                                <p class="font-medium">
                                    {{ i.product_name_snapshot }}
                                    <span v-if="i.is_bonus" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">BONUS</span>
                                </p>
                                <p class="text-[11px] text-muted-foreground font-mono">{{ i.product_sku_snapshot }} · {{ i.product_unit_name_snapshot }}</p>
                            </td>
                            <td class="py-2.5 px-3 font-mono text-xs">{{ i.batch_code_snapshot ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-xs">{{ formatDate(i.expired_date_snapshot) }}</td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_planned }}</td>
                            <td class="py-2.5 px-3 text-right">
                                <Input
                                    v-if="canConfirmPick"
                                    v-model="picks[i.id]"
                                    type="number"
                                    :min="0"
                                    :max="i.qty_planned"
                                    class="h-7 w-20 ml-auto text-right font-mono"
                                />
                                <span v-else class="font-mono">{{ i.qty_picked }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono">{{ i.qty_delivered }}</td>
                            <td class="py-2.5 px-5 text-right font-mono">{{ i.qty_returned }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bukti pengiriman (kalau delivered) -->
        <div v-if="deliveryOrder.proof_photo_signed_path" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5 mb-4">
            <h3 class="text-sm font-semibold mb-3">Bukti Pengiriman</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Foto Surat Jalan TTD</p>
                    <a :href="`/storage/${deliveryOrder.proof_photo_signed_path}`" target="_blank">
                        <img :src="`/storage/${deliveryOrder.proof_photo_signed_path}`" alt="proof" class="w-full h-48 object-cover rounded-md ring-1 ring-foreground/10" />
                    </a>
                </div>
                <div>
                    <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Penerima</p>
                    <p class="font-medium">{{ deliveryOrder.receiver_name }}</p>
                    <p v-if="deliveryOrder.receiver_notes" class="text-xs whitespace-pre-line mt-1">{{ deliveryOrder.receiver_notes }}</p>
                    <p v-if="deliveryOrder.delivery_latitude && deliveryOrder.delivery_longitude" class="text-xs text-muted-foreground mt-2 font-mono">
                        GPS: {{ deliveryOrder.delivery_latitude }}, {{ deliveryOrder.delivery_longitude }}
                    </p>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Riwayat</p>
            <ul class="text-xs space-y-1">
                <li>Dibuat <strong>{{ formatDateTime(deliveryOrder.created_at) }}</strong></li>
                <li v-if="deliveryOrder.picking_started_at">
                    Picking dimulai <strong>{{ formatDateTime(deliveryOrder.picking_started_at) }}</strong>
                    <span v-if="deliveryOrder.picking_starter"> · oleh {{ deliveryOrder.picking_starter.name }}</span>
                </li>
                <li v-if="deliveryOrder.packed_at">
                    Packed <strong>{{ formatDateTime(deliveryOrder.packed_at) }}</strong>
                    <span v-if="deliveryOrder.packer"> · oleh {{ deliveryOrder.packer.name }}</span>
                </li>
                <li v-if="deliveryOrder.in_transit_at" class="text-amber-700">
                    In Transit <strong>{{ formatDateTime(deliveryOrder.in_transit_at) }}</strong>
                </li>
                <li v-if="deliveryOrder.delivered_at" class="text-emerald-700">
                    Delivered <strong>{{ formatDateTime(deliveryOrder.delivered_at) }}</strong>
                    <span v-if="deliveryOrder.deliverer"> · oleh {{ deliveryOrder.deliverer.name }}</span>
                </li>
                <li v-if="deliveryOrder.cancelled_at" class="text-red-700">
                    Cancelled <strong>{{ formatDateTime(deliveryOrder.cancelled_at) }}</strong>
                    <span v-if="deliveryOrder.cancel_reason"> — "{{ deliveryOrder.cancel_reason }}"</span>
                </li>
            </ul>
        </div>

        <!-- Mark Packed Dialog -->
        <Dialog v-model:open="packedOpen">
            <DialogContent class="sm:max-w-[520px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <Package class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Mark Packed</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Pilih driver & vehicle. Sistem cek SIM & STNK expired. Surat jalan auto-generate.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4 space-y-3" @submit.prevent="doMarkPacked">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Driver *</Label>
                        <Select
                            :model-value="packedForm.driver_id ? String(packedForm.driver_id) : ''"
                            @update:model-value="(v) => (packedForm.driver_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger class="h-9"><SelectValue placeholder="Pilih driver" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="d in drivers" :key="d.id" :value="String(d.id)">
                                    {{ d.name }}
                                    <span v-if="driverLicenseStatus(d) === 'expired'" class="ml-1 text-red-700 text-[10px]">⚠ SIM expired</span>
                                    <span v-else-if="driverLicenseStatus(d) === 'expiring'" class="ml-1 text-amber-700 text-[10px]">⚠ SIM mau expired</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="packedForm.errors.driver_id" class="text-xs text-destructive">{{ packedForm.errors.driver_id }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Vehicle *</Label>
                        <Select
                            :model-value="packedForm.vehicle_id ? String(packedForm.vehicle_id) : ''"
                            @update:model-value="(v) => (packedForm.vehicle_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger class="h-9"><SelectValue placeholder="Pilih vehicle" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="vh in vehicles" :key="vh.id" :value="String(vh.id)">
                                    {{ vh.plate_number }} ({{ vh.type }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="packedForm.errors.vehicle_id" class="text-xs text-destructive">{{ packedForm.errors.vehicle_id }}</p>
                    </div>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="packedOpen = false">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="packedForm.processing || !packedForm.driver_id || !packedForm.vehicle_id" @click="doMarkPacked">
                        <Loader2 v-if="packedForm.processing" class="size-4 animate-spin" />
                        Mark Packed
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Mark Delivered Dialog -->
        <Dialog v-model:open="deliveredOpen">
            <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                            <PackageCheck class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Konfirmasi Delivery</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Upload foto SJ ber-TTD + isi qty actual. Stok ter-decrement.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4 space-y-3 max-h-[70vh] overflow-y-auto" @submit.prevent="doMarkDelivered">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1 col-span-2">
                            <Label class="text-xs font-medium">Nama Penerima *</Label>
                            <Input v-model="deliveredForm.receiver_name" required class="h-9" />
                            <p v-if="deliveredForm.errors.receiver_name" class="text-xs text-destructive">{{ deliveredForm.errors.receiver_name }}</p>
                        </div>
                        <div class="space-y-1 col-span-2">
                            <Label class="text-xs font-medium">Catatan Penerima</Label>
                            <Textarea v-model="deliveredForm.receiver_notes" rows="2" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Foto SJ Bertanda Tangan *</Label>
                        <Input type="file" accept="image/jpeg,image/png" class="h-9" @change="onProofPhotoChange" />
                        <p v-if="deliveredForm.errors.proof_photo" class="text-xs text-destructive">{{ deliveredForm.errors.proof_photo }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Digital Signature (opsional)</Label>
                        <Input type="file" accept="image/jpeg,image/png" class="h-9" @change="onSignatureChange" />
                    </div>

                    <div class="grid grid-cols-3 gap-2 items-end">
                        <div class="space-y-1 col-span-2">
                            <Label class="text-xs font-medium">GPS</Label>
                            <p class="text-xs font-mono">
                                <span v-if="deliveredForm.latitude && deliveredForm.longitude">
                                    {{ deliveredForm.latitude.toFixed(6) }}, {{ deliveredForm.longitude.toFixed(6) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="captureGps">Capture GPS</Button>
                    </div>

                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">Qty Actual per Item</p>
                        <div class="space-y-2">
                            <div v-for="(row, idx) in deliveredForm.item_quantities" :key="row.item_id" class="rounded-md ring-1 ring-foreground/10 p-2 grid grid-cols-3 gap-2 items-center">
                                <div class="text-xs">
                                    {{ deliveryOrder.items[idx].product_name_snapshot }}
                                    <span class="block text-[10px] text-muted-foreground font-mono">picked {{ deliveryOrder.items[idx].qty_picked }}</span>
                                </div>
                                <div>
                                    <Label class="text-[10px]">Delivered</Label>
                                    <Input v-model="row.qty_delivered" type="number" min="0" :max="deliveryOrder.items[idx].qty_picked" class="h-8 text-right font-mono" />
                                </div>
                                <div>
                                    <Label class="text-[10px]">Returned</Label>
                                    <Input v-model="row.qty_returned" type="number" min="0" :max="row.qty_delivered" class="h-8 text-right font-mono" />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="deliveredOpen = false">Batal</Button>
                    <Button type="button" variant="secondary" :disabled="deliveredForm.processing || !deliveredForm.proof_photo || !deliveredForm.receiver_name" @click="doMarkDelivered">
                        <Loader2 v-if="deliveredForm.processing" class="size-4 animate-spin" />
                        Konfirmasi Delivered
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Cancel Dialog -->
        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                    <div class="flex items-start gap-3">
                        <div class="size-10 rounded-md bg-red-50 text-red-700 flex items-center justify-center shrink-0 ring-1 ring-red-200">
                            <Ban class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base font-bold tracking-tight">Cancel DO</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Reservasi SO tetap aktif untuk DO baru.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <form class="px-5 py-4" @submit.prevent="doCancel">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="cancelForm.cancel_reason" rows="3" required class="mt-1" />
                    <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-destructive mt-1">{{ cancelForm.errors.cancel_reason }}</p>
                </form>
                <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                    <Button type="button" variant="outline" @click="cancelOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" :disabled="cancelForm.processing" @click="doCancel">Cancel DO</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
