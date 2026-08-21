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
    ShieldAlert,
    Store,
    Tags,
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
import { confirm } from '@/Composables/useConfirm';
import CreditLimitDialog from '@/Components/Customers/CreditLimitDialog.vue';
import PricePackageDialog from '@/Components/Customers/PricePackageDialog.vue';
import CustomerFormDialog from '@/Components/Customers/CustomerFormDialog.vue';
import PhotoUploadDialog from '@/Components/Customers/PhotoUploadDialog.vue';
import ReassignSalesDialog from '@/Components/Customers/ReassignSalesDialog.vue';
import SetGeoDialog from '@/Components/Customers/SetGeoDialog.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    types: { type: Array, required: true },
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
const creditLimitOpen = ref(false);
const pricePackageOpen = ref(false);

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

async function destroyPhoto(p) {
    if (!(await confirm({ title: 'Hapus foto ini?', destructive: true }))) return;
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

function daysOverdue(dueDate) {
    if (!dueDate) return 0;
    const due = new Date(dueDate);
    const now = new Date();
    const diff = Math.floor((now - due) / (1000 * 60 * 60 * 24));
    return Math.max(0, diff);
}

function overdueBadgeClass(dueDate) {
    const d = daysOverdue(dueDate);
    if (d > 90) return 'bg-red-50 text-red-700';
    if (d > 60) return 'bg-amber-50 text-amber-700';
    return 'bg-muted/70 text-muted-foreground';
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
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('customers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Customer
                    </Link>
                </Button>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="editOpen = true"
                >
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
                class="rounded-2xl bg-warning-soft ring-1 ring-warning/30 p-3.5 flex items-center gap-3 mb-2"
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
                <Button size="sm" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="approvePending(p)">
                    <Check class="size-3.5" /> Approve
                </Button>
                <Button size="sm" variant="outline" class="rounded-full" @click="rejectPending(p)">
                    <X class="size-3.5" /> Reject
                </Button>
            </div>
        </section>

        <!-- Compact summary + tabs -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm mb-4 overflow-hidden">
            <div class="px-4 py-3 flex flex-wrap items-center gap-3 border-b border-foreground/5">
                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                    <Store class="size-4" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ customer.code }}
                        <span v-if="customer.assigned_sales"> · Sales {{ customer.assigned_sales.name }}</span>
                    </p>
                    <h2 class="text-sm font-bold tracking-tight text-foreground truncate leading-tight">
                        {{ customer.name }}
                    </h2>
                </div>
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                        customer.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                    ]"
                >
                    <span
                        :class="[
                            'size-1.5 rounded-full',
                            customer.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                        ]"
                    />
                    {{ customer.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
                <div v-if="canUpdate" class="flex items-center gap-1.5">
                    <Button variant="outline" size="sm" class="rounded-full" @click="creditLimitOpen = true">
                        <ShieldAlert class="size-3.5" />
                        Credit Limit
                    </Button>
                    <Button variant="outline" size="sm" class="rounded-full" @click="pricePackageOpen = true">
                        <Tags class="size-3.5" />
                        Paket Harga
                    </Button>
                    <Button variant="outline" size="sm" class="rounded-full" @click="reassignOpen = true">
                        <UserCog class="size-3.5" />
                        Reassign Sales
                    </Button>
                    <Button variant="outline" size="sm" class="rounded-full" @click="toggleActive">
                        <component :is="customer.is_active ? Ban : UserCheck" class="size-3.5" />
                        {{ customer.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </Button>
                </div>
            </div>
            <div class="px-4 py-2.5">
                <TabsPill v-model="tab" :tabs="tabs" tone="brand" />
            </div>
        </section>

        <!-- Tab: Info -->
        <section v-show="tab === 'info'" class="space-y-3">
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <dl class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-5 gap-y-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Pemilik</dt>
                        <dd class="mt-0.5">{{ customer.owner_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tipe</dt>
                        <dd class="mt-0.5">{{ customer.type?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">No. WhatsApp</dt>
                        <dd class="font-mono mt-0.5">{{ customer.whatsapp || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Area Kerja</dt>
                        <dd class="mt-0.5">{{ customer.area || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Payment Term</dt>
                        <dd class="mt-0.5">{{ customer.payment_term_days }} hari</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Credit Limit</dt>
                        <dd class="mt-0.5 text-muted-foreground text-[13px]">Diatur per supplier — lihat tombol <strong>Credit Limit</strong> di atas.</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Dibuat</dt>
                        <dd class="mt-0.5">{{ formatDate(customer.created_at) }}</dd>
                    </div>
                </dl>
                <div v-if="customer.notes" class="border-t border-foreground/5 px-5 py-3 text-sm">
                    <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Catatan</p>
                    <p class="text-foreground whitespace-pre-line">{{ customer.notes }}</p>
                </div>
            </div>
        </section>

        <!-- Tab: Lokasi -->
        <section v-show="tab === 'lokasi'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
            <header class="border-b border-foreground/5 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Koordinat GPS</h3>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="geoOpen = true"
                >
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
        <section v-show="tab === 'photos'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <header class="border-b border-foreground/5 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Foto Outlet</h3>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="photoOpen = true"
                >
                    <Camera class="size-4" />
                    Upload
                </Button>
            </header>
            <div v-if="customer.photos?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-4">
                <div
                    v-for="p in customer.photos"
                    :key="p.id"
                    class="rounded-2xl ring-1 ring-foreground/10 overflow-hidden bg-muted relative group"
                >
                    <img :src="photoUrl(p.file_path)" :alt="p.caption || p.type" class="w-full h-32 object-cover" />
                    <div class="absolute top-1.5 left-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-black/60 text-white">
                        {{ p.type }}
                    </div>
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-end justify-end p-2 opacity-0 group-hover:opacity-100">
                        <ActionGroup v-if="canUpdate" class="rounded-full">
                            <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroyPhoto(p)" />
                        </ActionGroup>
                    </div>
                    <p v-if="p.caption" class="px-2 py-1 text-[12px] truncate">{{ p.caption }}</p>
                </div>
            </div>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Image class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada foto.
            </div>
        </section>

        <!-- Tab: Outstanding -->
        <section v-show="tab === 'ar'" class="space-y-3">
            <!-- Total + Aging strip -->
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-foreground/5">
                    <div class="px-4 py-3 sm:col-span-1">
                        <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Total Outstanding</p>
                        <p class="text-lg font-bold font-mono mt-0.5">{{ formatRupiah(outstanding.total) }}</p>
                    </div>
                    <div
                        v-for="(amt, bucket) in outstanding.aging"
                        :key="bucket"
                        class="px-4 py-3"
                    >
                        <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">
                            {{ bucket }} hari
                        </p>
                        <p
                            :class="[
                                'font-mono text-sm mt-0.5',
                                bucket === '90+' && Number(amt) > 0 ? 'text-destructive font-semibold' : '',
                                bucket === '61-90' && Number(amt) > 0 ? 'text-amber-700 font-semibold' : '',
                            ]"
                        >
                            {{ formatRupiah(amt) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Invoice list -->
            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
                <header class="border-b border-foreground/5 px-4 py-2.5">
                    <h3 class="text-sm font-semibold">Invoice belum lunas</h3>
                </header>
                <div v-if="outstanding.invoices?.length" class="divide-y divide-foreground/5">
                    <div
                        v-for="inv in outstanding.invoices"
                        :key="inv.id"
                        class="px-4 py-2.5 flex items-center gap-3 text-sm hover:bg-foreground/2.5 transition-colors"
                    >
                        <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                            <DollarSign class="size-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-mono font-medium truncate">{{ inv.invoice_number }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(inv.invoice_date) }} · jatuh tempo {{ formatDate(inv.due_date) }}
                                <span
                                    v-if="daysOverdue(inv.due_date) > 0"
                                    class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                    :class="overdueBadgeClass(inv.due_date)"
                                >
                                    Lewat {{ daysOverdue(inv.due_date) }} hari
                                </span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono font-semibold text-sm">{{ formatRupiah(inv.outstanding) }}</p>
                            <p class="text-[12px] text-muted-foreground">
                                dari {{ formatRupiah(inv.total) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                    <DollarSign class="size-7 opacity-40 mx-auto mb-2" />
                    Tidak ada invoice belum lunas.
                </div>
            </div>
        </section>

        <!-- Modals -->
        <CustomerFormDialog
            v-model:open="editOpen"
            :customer="customer"
            :types="types"
            :sales-users="salesUsers"
            :can-edit-credit-limit="false"
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
        <CreditLimitDialog
            v-model:open="creditLimitOpen"
            :customer="customer"
            :can-update="canUpdate"
            @saved="onSaved"
        />
        <PricePackageDialog
            v-model:open="pricePackageOpen"
            :customer="customer"
            :can-update="canUpdate"
            @saved="onSaved"
        />
    </AppLayout>
</template>
