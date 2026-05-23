<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Camera,
    Check,
    DollarSign,
    Edit,
    Image,
    Info,
    MapPin,
    Store,
    Trash2,
    UserCog,
    UserCheck,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import CustomerFormDialog from '@/Components/Customers/CustomerFormDialog.vue';
import PhotoUploadDialog from '@/Components/Customers/PhotoUploadDialog.vue';
import ReassignSalesDialog from '@/Components/Customers/ReassignSalesDialog.vue';
import SetGeoDialog from '@/Components/Customers/SetGeoDialog.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    types: { type: Array, required: true },
    tiers: { type: Array, required: true },
    salesUsers: { type: Array, required: true },
    outstanding: { type: Object, default: () => ({ total: 0, aging: {} }) },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canApproveGeo: { type: Boolean, default: false },
});

const tab = ref('info');
const tabs = computed(() => [
    { value: 'info', label: 'Info', icon: Info },
    { value: 'lokasi', label: 'Lokasi', icon: MapPin },
    { value: 'photos', label: `Foto (${props.customer.photos?.length ?? 0})`, icon: Image },
    { value: 'ar', label: 'Outstanding', icon: DollarSign },
]);

const editOpen = ref(false);
const geoOpen = ref(false);
const reassignOpen = ref(false);
const photoOpen = ref(false);

const pendingGeos = computed(() => props.customer.geo_pendings ?? []);

function onSaved() {
    router.reload({ only: ['customer', 'outstanding'] });
}

function toggleActive() {
    useForm({}).post(route('customers.toggle-active', props.customer.id), {
        preserveScroll: true,
    });
}

function approvePending(p) {
    useForm({}).post(route('customers.geo-pending.approve', [props.customer.id, p.id]), {
        preserveScroll: true,
    });
}

function rejectPending(p) {
    const reason = window.prompt('Alasan tolak?');
    if (!reason) return;
    useForm({ reason }).post(route('customers.geo-pending.reject', [props.customer.id, p.id]), {
        preserveScroll: true,
    });
}

function destroyPhoto(p) {
    if (!window.confirm('Hapus foto ini?')) return;
    useForm({}).delete(route('customer-photos.destroy', p.id), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatRupiah(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function photoUrl(path) {
    return `/storage/${path}`;
}
</script>

<template>
    <Head :title="`Customer: ${customer.name}`" />

    <AppLayout>
        <PageHeader
            :title="customer.name"
            :description="`${customer.code} · ${customer.type?.name ?? 'Tanpa tipe'}`"
            :icon="Store"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('customers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Customer
                    </Link>
                </Button>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="editOpen = true">
                    <Edit class="size-4" />
                    Edit
                </Button>
            </template>
        </PageHeader>

        <!-- Pending geo banner -->
        <section v-if="canApproveGeo && pendingGeos.length" class="mb-4">
            <div
                v-for="p in pendingGeos"
                :key="p.id"
                class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-3 flex items-center gap-3 mb-2"
            >
                <MapPin class="size-5 text-amber-700 shrink-0" />
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-medium text-amber-900">
                        Pending koordinat dari {{ p.captured_by?.name ?? 'sales' }} ({{ formatDate(p.captured_at) }})
                    </p>
                    <p class="text-xs text-amber-800 font-mono">
                        {{ p.captured_latitude }}, {{ p.captured_longitude }}
                        <span v-if="p.accuracy_meter"> · ±{{ p.accuracy_meter }}m</span>
                    </p>
                </div>
                <Button size="sm" variant="secondary" @click="approvePending(p)">
                    <Check class="size-3.5" /> Approve
                </Button>
                <Button size="sm" variant="outline" @click="rejectPending(p)">
                    <X class="size-3.5" /> Reject
                </Button>
            </div>
        </section>

        <!-- Summary strip -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Store class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ customer.code }} · {{ customer.price_tier?.name ?? '—' }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate">
                        {{ customer.name }}
                    </h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ [customer.city, customer.province].filter(Boolean).join(', ') || 'Alamat belum diisi' }}
                        <span v-if="customer.assigned_sales"> · Sales: {{ customer.assigned_sales.name }}</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span
                        v-if="customer.is_active"
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                    >
                        Aktif
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                    >
                        Nonaktif
                    </span>
                </div>
            </div>
            <div v-if="canUpdate" class="flex flex-row lg:flex-col gap-2 lg:items-stretch lg:justify-center">
                <Button variant="outline" size="default" @click="reassignOpen = true">
                    <UserCog class="size-4" />
                    Reassign Sales
                </Button>
                <Button variant="outline" size="default" @click="toggleActive">
                    <component :is="customer.is_active ? Ban : UserCheck" class="size-4" />
                    {{ customer.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </div>
        </section>

        <div class="mb-4">
            <TabsPill v-model="tab" :tabs="tabs" />
        </div>

        <!-- Tab: Info -->
        <section v-show="tab === 'info'" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Identitas</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kode</dt>
                            <dd class="font-mono mt-0.5">{{ customer.code }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tipe</dt>
                            <dd class="mt-0.5">{{ customer.type?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Pemilik</dt>
                            <dd class="mt-0.5">{{ customer.owner_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">NPWP</dt>
                            <dd class="font-mono mt-0.5">{{ customer.npwp || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Price Tier</dt>
                            <dd class="mt-0.5">{{ customer.price_tier?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Sales</dt>
                            <dd class="mt-0.5">{{ customer.assigned_sales?.name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Kontak</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Phone</dt>
                            <dd class="font-mono mt-0.5">{{ customer.phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">WhatsApp</dt>
                            <dd class="font-mono mt-0.5">{{ customer.whatsapp || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Email</dt>
                            <dd class="mt-0.5">{{ customer.email || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Alamat</h3>
                    </header>
                    <div class="px-5 py-3 text-sm">
                        <p v-if="customer.address" class="whitespace-pre-line">{{ customer.address }}</p>
                        <p v-else class="text-muted-foreground italic">Alamat belum diisi</p>
                        <p class="text-xs text-muted-foreground mt-2">
                            {{ [customer.city, customer.province, customer.postal_code].filter(Boolean).join(', ') }}
                            <span v-if="customer.area"> · Area: {{ customer.area }}</span>
                        </p>
                    </div>
                </div>

                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Finance</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Credit Limit</dt>
                            <dd class="font-mono mt-0.5">{{ formatRupiah(customer.credit_limit) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                            <dd class="mt-0.5">{{ customer.payment_term_days }} hari</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Dibuat</dt>
                            <dd class="mt-0.5">{{ formatDate(customer.created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="customer.notes" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm px-5 py-3">
                <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
                <p class="text-sm text-foreground whitespace-pre-line">{{ customer.notes }}</p>
            </div>
        </section>

        <!-- Tab: Lokasi -->
        <section v-show="tab === 'lokasi'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Koordinat GPS</h3>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="geoOpen = true">
                    <MapPin class="size-4" />
                    {{ customer.latitude ? 'Ubah Koordinat' : 'Set Koordinat' }}
                </Button>
            </header>
            <div class="px-5 py-4 space-y-3 text-sm">
                <div v-if="customer.latitude && customer.longitude">
                    <p class="font-mono text-base">
                        {{ customer.latitude }}, {{ customer.longitude }}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                        Dikonfirmasi {{ formatDate(customer.geo_confirmed_at) }}
                    </p>
                    <a
                        :href="`https://www.google.com/maps?q=${customer.latitude},${customer.longitude}`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 text-xs text-primary hover:underline mt-2"
                    >
                        <MapPin class="size-3" />
                        Lihat di Google Maps
                    </a>
                </div>
                <div v-else class="text-center py-10 text-muted-foreground">
                    <MapPin class="size-7 opacity-40 mx-auto mb-2" />
                    Belum ada koordinat.
                </div>
            </div>
        </section>

        <!-- Tab: Foto -->
        <section v-show="tab === 'photos'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Foto Outlet</h3>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="photoOpen = true">
                    <Camera class="size-4" />
                    Upload
                </Button>
            </header>
            <div v-if="customer.photos?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-4">
                <div
                    v-for="p in customer.photos"
                    :key="p.id"
                    class="rounded-md ring-1 ring-foreground/10 overflow-hidden bg-muted relative group"
                >
                    <img :src="photoUrl(p.file_path)" :alt="p.caption || p.type" class="w-full h-32 object-cover" />
                    <div class="absolute top-1 left-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black/60 text-white">
                        {{ p.type }}
                    </div>
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-end justify-end p-2 opacity-0 group-hover:opacity-100">
                        <ActionGroup v-if="canUpdate">
                            <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroyPhoto(p)" />
                        </ActionGroup>
                    </div>
                    <p v-if="p.caption" class="px-2 py-1 text-[11px] truncate">{{ p.caption }}</p>
                </div>
            </div>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Image class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada foto.
            </div>
        </section>

        <!-- Tab: Outstanding -->
        <section v-show="tab === 'ar'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
            <header class="border-b border-border/70 px-5 py-3">
                <h3 class="text-sm font-semibold">AR Outstanding</h3>
            </header>
            <div class="px-5 py-4 space-y-4">
                <div class="rounded-md bg-muted/40 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Total Outstanding</p>
                    <p class="text-xl font-bold font-mono mt-1">{{ formatRupiah(outstanding.total) }}</p>
                    <p class="text-[11px] text-muted-foreground mt-1">
                        Limit: {{ formatRupiah(customer.credit_limit) }}
                    </p>
                </div>
                <div>
                    <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-2">AR Aging</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div v-for="(amt, bucket) in outstanding.aging" :key="bucket" class="rounded-md ring-1 ring-foreground/10 px-3 py-2">
                            <p class="text-[10px] uppercase tracking-wider text-muted-foreground">{{ bucket }} hari</p>
                            <p class="font-mono text-sm mt-0.5">{{ formatRupiah(amt) }}</p>
                        </div>
                    </div>
                </div>
                <p class="text-[11px] text-muted-foreground italic">
                    Detail invoice akan tampil setelah modul Invoice (T13) ter-landing.
                </p>
            </div>
        </section>

        <!-- Modals -->
        <CustomerFormDialog
            v-model:open="editOpen"
            :customer="customer"
            :types="types"
            :tiers="tiers"
            :sales-users="salesUsers"
            :can-edit-credit-limit="false"
            :can-edit-price-tier="canUpdate"
            :can-edit-assigned-sales="canUpdate"
            @saved="onSaved"
        />
        <SetGeoDialog v-model:open="geoOpen" :customer="customer" @saved="onSaved" />
        <ReassignSalesDialog
            v-model:open="reassignOpen"
            :customer="customer"
            :sales-users="salesUsers"
            @saved="onSaved"
        />
        <PhotoUploadDialog v-model:open="photoOpen" :customer-id="customer.id" @saved="onSaved" />
    </AppLayout>
</template>
