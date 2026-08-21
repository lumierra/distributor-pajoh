<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, MapPin, XCircle } from '@lucide/vue';
import { ref } from 'vue';
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
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    visit: { type: Object, required: true },
    canCancel: { type: Boolean, default: false },
    canCheckout: { type: Boolean, default: false },
});

const cancelOpen = ref(false);
const reason = ref('');
const processing = ref(false);

function submitCancel() {
    processing.value = true;
    router.post(route('sales-visits.cancel', props.visit.id), { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => (cancelOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID');
}

function fmtDuration(min) {
    if (min === null || min === undefined) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}j ${m}m` : `${m}m`;
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

const VISIT_STATUS = {
    active: { label: 'Aktif', text: 'text-blue-700', dot: 'bg-blue-500' },
    completed: { label: 'Selesai', text: 'text-emerald-700', dot: 'bg-emerald-600' },
    cancelled: { label: 'Dibatalkan', text: 'text-red-700', dot: 'bg-red-400' },
};
function visitStatus(s) {
    return VISIT_STATUS[s] ?? { label: s, text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' };
}
</script>

<template>
    <Head :title="`Visit #${visit.id}`" />

    <AppLayout>
        <PageHeader :title="`Visit #${visit.id}`" :description="`${visit.sales?.name ?? '—'} @ ${visit.customer?.name ?? '—'}`" :icon="MapPin">
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('sales-visits.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
                <Button v-if="canCancel" variant="outline" size="default" class="rounded-full text-red-700" @click="cancelOpen = true">
                    <XCircle class="size-4" /> Cancel
                </Button>
            </template>
        </PageHeader>

        <div v-if="visit.status === 'cancelled'" class="mb-4 rounded-2xl ring-1 ring-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800 flex items-start gap-2">
            <AlertTriangle class="size-4 mt-0.5" />
            <div>
                <p class="font-semibold">Visit dibatalkan</p>
                <p class="text-xs">{{ visit.cancel_reason }}</p>
            </div>
        </div>

        <div v-if="visit.is_mock_location" class="mb-4 rounded-2xl ring-1 ring-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 flex items-center gap-2">
            <AlertTriangle class="size-4" />
            <span>Mock location terdeteksi pada check-in.</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Header</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Sales</dt><dd>{{ visit.sales?.name ?? '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Customer</dt><dd>{{ visit.customer?.name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-[12px] uppercase text-muted-foreground">Status</dt>
                        <dd>
                            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium', visitStatus(visit.status).text]">
                                <span :class="['size-1.5 rounded-full', visitStatus(visit.status).dot]" />
                                {{ visitStatus(visit.status).label }}
                            </span>
                        </dd>
                    </div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Device</dt><dd>{{ visit.device?.device_name ?? '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Check-in</dt><dd>{{ fmt(visit.checked_in_at) }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Check-out</dt><dd>{{ fmt(visit.checked_out_at) }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Durasi</dt><dd>{{ fmtDuration(visit.duration_minutes) }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Distance to outlet</dt><dd>{{ visit.checkin_distance_to_outlet !== null ? `${visit.checkin_distance_to_outlet}m` : '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Lat/Lng</dt><dd class="font-mono text-xs">{{ visit.checkin_latitude }}, {{ visit.checkin_longitude }}</dd></div>
                    <div v-if="visit.bypass_geofence"><dt class="text-[12px] uppercase text-amber-700">Geofence Bypass</dt><dd>YES</dd></div>
                </dl>
                <div v-if="visit.auto_checked_out" class="text-xs text-muted-foreground border-t pt-2">
                    Auto-checked-out — reason: {{ visit.auto_checkout_reason }}
                </div>
            </section>

            <aside class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4 space-y-2">
                <h3 class="text-sm font-semibold">Aktivitas</h3>
                <div class="text-sm">
                    <p class="flex justify-between">
                        <span class="text-muted-foreground">SO Count</span>
                        <span class="font-semibold">{{ visit.so_count }}</span>
                    </p>
                    <p class="flex justify-between">
                        <span class="text-muted-foreground">SO Value</span>
                        <span class="font-mono">{{ fmtRp(visit.so_total_value) }}</span>
                    </p>
                    <p class="flex justify-between">
                        <span class="text-muted-foreground">Returns</span>
                        <span>{{ visit.return_count }}</span>
                    </p>
                    <p class="flex justify-between">
                        <span class="text-muted-foreground">Photos</span>
                        <span>{{ visit.photo_count }}</span>
                    </p>
                </div>
            </aside>

            <section v-if="(visit.sales_orders ?? []).length > 0" class="lg:col-span-3 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-4">
                <h3 class="text-sm font-semibold mb-2">Sales Orders</h3>
                <ul class="text-sm space-y-1">
                    <li v-for="so in visit.sales_orders" :key="so.id" class="flex justify-between gap-2 py-1 border-b border-foreground/5 last:border-0">
                        <Link :href="route('sales-orders.show', so.id)" class="font-mono text-xs hover:text-primary">{{ so.so_number }}</Link>
                        <span class="text-xs text-muted-foreground">{{ so.status }}</span>
                        <span class="font-mono text-xs">{{ fmtRp(so.total) }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <XCircle class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Batalkan Visit</DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">Beri alasan pembatalan.</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
                <div class="px-6 pb-2">
                    <Label class="text-xs font-medium">Alasan *</Label>
                    <Textarea v-model="reason" rows="3" required class="mt-1.5 rounded-xl" />
                </div>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button variant="outline" class="rounded-full" @click="cancelOpen = false">Batal</Button>
                    <Button variant="destructive" class="rounded-full" :disabled="processing || !reason.trim()" @click="submitCancel">Batalkan Visit</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
