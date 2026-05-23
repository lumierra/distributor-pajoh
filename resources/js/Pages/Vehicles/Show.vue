<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Edit,
    FileText,
    Info,
    Plus,
    Trash2,
    Truck,
    UserCheck,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import SetMaintenanceDialog from '@/Components/Vehicles/SetMaintenanceDialog.vue';
import VehicleDocumentDialog from '@/Components/Vehicles/VehicleDocumentDialog.vue';
import VehicleFormDialog from '@/Components/Vehicles/VehicleFormDialog.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    types: { type: Array, required: true },
    canUpdate: { type: Boolean, default: false },
});

const tab = ref('info');
const tabs = computed(() => [
    { value: 'info', label: 'Info', icon: Info },
    { value: 'documents', label: `Dokumen (${props.vehicle.documents?.length ?? 0})`, icon: FileText },
]);

const editOpen = ref(false);
const docOpen = ref(false);
const maintOpen = ref(false);

function onSaved() {
    router.reload({ only: ['vehicle'] });
}

function toggleActive() {
    useForm({}).post(route('vehicles.toggle-active', props.vehicle.id), { preserveScroll: true });
}

function unsetMaintenance() {
    useForm({}).delete(route('vehicles.maintenance.unset', props.vehicle.id), { preserveScroll: true });
}

function destroyDoc(d) {
    if (!window.confirm(`Hapus dokumen "${d.title}"?`)) return;
    useForm({}).delete(route('vehicle-documents.destroy', d.id), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatBytes(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function docExpiryBadge(d) {
    if (!d.expires_date) return null;
    const days = Math.ceil((new Date(d.expires_date) - new Date()) / (1000 * 60 * 60 * 24));
    if (days < 0) return { label: `Expired ${Math.abs(days)}h lalu`, class: 'bg-red-50 text-red-700 ring-red-200' };
    if (days <= 30) return { label: `Expire ${days}h lagi`, class: 'bg-warning-soft text-amber-700 ring-warning/30' };
    return null;
}

const statusBadge = computed(() => {
    const s = props.vehicle.status;
    const map = {
        idle: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        on_delivery: 'bg-blue-50 text-blue-700 ring-blue-200',
        maintenance: 'bg-amber-50 text-amber-800 ring-amber-200',
    };
    return map[s] ?? 'bg-muted text-muted-foreground';
});
</script>

<template>
    <Head :title="`Vehicle: ${vehicle.plate_number}`" />

    <AppLayout>
        <PageHeader
            :title="vehicle.plate_number"
            :description="`${vehicle.code} · ${vehicle.brand ?? ''} ${vehicle.model ?? ''}`.trim()"
            :icon="Truck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('vehicles.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Vehicle
                    </Link>
                </Button>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="editOpen = true">
                    <Edit class="size-4" />
                    Edit
                </Button>
            </template>
        </PageHeader>

        <!-- Maintenance banner -->
        <section v-if="vehicle.status === 'maintenance'" class="mb-4">
            <div class="rounded-lg bg-warning-soft ring-1 ring-warning/30 p-3 flex items-center gap-3">
                <Wrench class="size-5 text-amber-700 shrink-0" />
                <div class="flex-1 min-w-0 text-sm">
                    <p class="font-medium text-amber-900">Vehicle sedang maintenance</p>
                    <p v-if="vehicle.notes" class="text-xs text-amber-800 whitespace-pre-line">{{ vehicle.notes }}</p>
                </div>
                <Button v-if="canUpdate" size="sm" variant="outline" @click="unsetMaintenance">
                    Keluar Maintenance
                </Button>
            </div>
        </section>

        <!-- Summary strip -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Truck class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ vehicle.code }} · {{ vehicle.type }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate font-mono">
                        {{ vehicle.plate_number }}
                    </h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ vehicle.brand }} {{ vehicle.model }}<span v-if="vehicle.year"> · {{ vehicle.year }}</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1', statusBadge]">
                        {{ vehicle.status }}
                    </span>
                    <span
                        v-if="vehicle.is_active"
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                    >Aktif</span>
                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">Nonaktif</span>
                </div>
            </div>
            <div v-if="canUpdate" class="flex flex-row lg:flex-col gap-2 lg:items-stretch lg:justify-center">
                <Button
                    v-if="vehicle.status !== 'maintenance'"
                    variant="outline"
                    size="default"
                    @click="maintOpen = true"
                >
                    <Wrench class="size-4" />
                    Maintenance
                </Button>
                <Button variant="outline" size="default" @click="toggleActive">
                    <component :is="vehicle.is_active ? Ban : UserCheck" class="size-4" />
                    {{ vehicle.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </div>
        </section>

        <div class="mb-4">
            <TabsPill v-model="tab" :tabs="tabs" />
        </div>

        <!-- Tab: Info -->
        <section v-show="tab === 'info'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Identitas</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kode</dt>
                        <dd class="font-mono mt-0.5">{{ vehicle.code }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Plat</dt>
                        <dd class="font-mono mt-0.5">{{ vehicle.plate_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tipe</dt>
                        <dd class="mt-0.5">{{ vehicle.type }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tahun</dt>
                        <dd class="font-mono mt-0.5">{{ vehicle.year || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Brand</dt>
                        <dd class="mt-0.5">{{ vehicle.brand || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Model</dt>
                        <dd class="mt-0.5">{{ vehicle.model || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Warna</dt>
                        <dd class="mt-0.5">{{ vehicle.color || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Kapasitas & Service</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kapasitas</dt>
                        <dd class="font-mono mt-0.5">
                            <span v-if="vehicle.capacity_kg">{{ Number(vehicle.capacity_kg).toLocaleString('id-ID') }} kg</span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Volume</dt>
                        <dd class="font-mono mt-0.5">
                            <span v-if="vehicle.capacity_kubik">{{ Number(vehicle.capacity_kubik).toLocaleString('id-ID') }} m³</span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Service Terakhir</dt>
                        <dd class="mt-0.5">{{ formatDate(vehicle.last_service_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Service Berikut</dt>
                        <dd class="mt-0.5">{{ formatDate(vehicle.next_service_date) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Odometer</dt>
                        <dd class="font-mono mt-0.5">
                            <span v-if="vehicle.odometer_km">{{ Number(vehicle.odometer_km).toLocaleString('id-ID') }} km</span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="vehicle.drivers?.length" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm lg:col-span-2">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Driver Default ({{ vehicle.drivers.length }})</h3>
                </header>
                <ul class="px-5 py-3 text-sm space-y-1">
                    <li v-for="d in vehicle.drivers" :key="d.id" class="flex items-center gap-2">
                        <span class="font-medium">{{ d.name }}</span>
                        <span class="text-xs text-muted-foreground font-mono">{{ d.code }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="vehicle.notes" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm lg:col-span-2">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Catatan</h3>
                </header>
                <p class="px-5 py-3 text-sm whitespace-pre-line">{{ vehicle.notes }}</p>
            </div>
        </section>

        <!-- Tab: Dokumen -->
        <section v-show="tab === 'documents'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Dokumen</h3>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="docOpen = true">
                    <Plus class="size-4" />
                    Upload
                </Button>
            </header>
            <ul v-if="vehicle.documents?.length" class="divide-y divide-border/60">
                <li
                    v-for="d in vehicle.documents"
                    :key="d.id"
                    class="px-5 py-3 flex items-center gap-3 hover:bg-muted/30 transition-colors"
                >
                    <div class="size-9 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <FileText class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-medium text-foreground truncate">{{ d.title }}</p>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground">
                                {{ d.type }}
                            </span>
                            <span
                                v-if="docExpiryBadge(d)"
                                :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ring-1', docExpiryBadge(d).class]"
                            >
                                {{ docExpiryBadge(d).label }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="d.issued_date">Terbit {{ formatDate(d.issued_date) }}</span>
                            <span v-if="d.expires_date"> · Expired {{ formatDate(d.expires_date) }}</span>
                            <span class="ml-1">· {{ formatBytes(d.file_size) }}</span>
                            <span v-if="d.uploader" class="ml-1">· oleh {{ d.uploader.name }}</span>
                        </p>
                    </div>
                    <ActionGroup>
                        <ActionButton :icon="FileText" label="Lihat" as-child tone="brand">
                            <a :href="route('vehicle-documents.show', d.id)" target="_blank" rel="noopener">
                                <FileText class="w-4 h-4" />
                            </a>
                        </ActionButton>
                        <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroyDoc(d)" />
                    </ActionGroup>
                </li>
            </ul>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <FileText class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada dokumen.
            </div>
        </section>

        <VehicleFormDialog v-model:open="editOpen" :vehicle="vehicle" :types="types" @saved="onSaved" />
        <VehicleDocumentDialog v-model:open="docOpen" :vehicle-id="vehicle.id" @saved="onSaved" />
        <SetMaintenanceDialog v-model:open="maintOpen" :vehicle="vehicle" @saved="onSaved" />
    </AppLayout>
</template>
