<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Edit,
    FileText,
    Info,
    Pause,
    Plus,
    Trash2,
    Truck,
    UserCheck,
    UserCog,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import DocumentUploadDialog from '@/Components/Drivers/DocumentUploadDialog.vue';
import DriverFormDialog from '@/Components/Drivers/DriverFormDialog.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    driver: { type: Object, required: true },
    vehicles: { type: Array, required: true },
    canUpdate: { type: Boolean, default: false },
});

const tab = ref('info');
const tabs = computed(() => [
    { value: 'info', label: 'Info', icon: Info },
    { value: 'documents', label: `Dokumen (${props.driver.documents?.length ?? 0})`, icon: FileText },
]);

const editOpen = ref(false);
const docOpen = ref(false);

function onSaved() {
    router.reload({ only: ['driver'] });
}

function toggleActive() {
    useForm({}).post(route('drivers.toggle-active', props.driver.id), { preserveScroll: true });
}

function setUnavailable() {
    useForm({}).post(route('drivers.set-unavailable', props.driver.id), { preserveScroll: true });
}

function destroyDoc(d) {
    if (!window.confirm(`Hapus dokumen "${d.title}"?`)) return;
    useForm({}).delete(route('driver-documents.destroy', d.id), { preserveScroll: true });
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
</script>

<template>
    <Head :title="`Driver: ${driver.name}`" />

    <AppLayout>
        <PageHeader
            :title="driver.name"
            :description="`${driver.code} · ${driver.phone ?? 'tanpa phone'}`"
            :icon="UserCog"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('drivers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Driver
                    </Link>
                </Button>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="editOpen = true">
                    <Edit class="size-4" />
                    Edit
                </Button>
            </template>
        </PageHeader>

        <!-- Summary strip -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <UserCog class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ driver.code }} · {{ driver.license_type || 'Tanpa SIM' }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate">{{ driver.name }}</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        <span v-if="driver.default_vehicle">
                            <Truck class="inline size-3" /> {{ driver.default_vehicle.plate_number }}
                        </span>
                        <span v-else>Tanpa default vehicle</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">
                        {{ driver.status }}
                    </span>
                    <span
                        v-if="driver.is_active"
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                    >Aktif</span>
                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">Nonaktif</span>
                </div>
            </div>
            <div v-if="canUpdate" class="flex flex-row lg:flex-col gap-2 lg:items-stretch lg:justify-center">
                <Button variant="outline" size="default" @click="setUnavailable">
                    <Pause class="size-4" />
                    {{ driver.status === 'unavailable' ? 'Idle-kan' : 'Unavailable' }}
                </Button>
                <Button variant="outline" size="default" @click="toggleActive">
                    <component :is="driver.is_active ? Ban : UserCheck" class="size-4" />
                    {{ driver.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
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
                        <dd class="font-mono mt-0.5">{{ driver.code }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">NIK</dt>
                        <dd class="font-mono mt-0.5">{{ driver.nik || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Phone</dt>
                        <dd class="font-mono mt-0.5">{{ driver.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">WhatsApp</dt>
                        <dd class="font-mono mt-0.5">{{ driver.whatsapp || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Alamat</dt>
                        <dd class="mt-0.5">{{ driver.address || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">SIM & Operasional</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">No SIM</dt>
                        <dd class="font-mono mt-0.5">{{ driver.license_no || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tipe SIM</dt>
                        <dd class="mt-0.5">{{ driver.license_type || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">SIM Expired</dt>
                        <dd class="mt-0.5">{{ formatDate(driver.license_expired_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Tgl Masuk</dt>
                        <dd class="mt-0.5">{{ formatDate(driver.hire_date) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Default Vehicle</dt>
                        <dd class="mt-0.5">
                            <span v-if="driver.default_vehicle" class="font-mono">
                                {{ driver.default_vehicle.plate_number }} ({{ driver.default_vehicle.code }})
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm lg:col-span-2">
                <header class="border-b border-border/70 px-5 py-3">
                    <h3 class="text-sm font-semibold">Kontak Darurat & Catatan</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Nama</dt>
                        <dd class="mt-0.5">{{ driver.emergency_contact_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Phone</dt>
                        <dd class="font-mono mt-0.5">{{ driver.emergency_contact_phone || '—' }}</dd>
                    </div>
                    <div v-if="driver.notes" class="sm:col-span-2">
                        <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Catatan</dt>
                        <dd class="mt-0.5 whitespace-pre-line">{{ driver.notes }}</dd>
                    </div>
                </dl>
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
            <ul v-if="driver.documents?.length" class="divide-y divide-border/60">
                <li
                    v-for="d in driver.documents"
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
                            <a :href="route('driver-documents.show', d.id)" target="_blank" rel="noopener">
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

        <DriverFormDialog v-model:open="editOpen" :driver="driver" :vehicles="vehicles" @saved="onSaved" />
        <DocumentUploadDialog v-model:open="docOpen" :driver-id="driver.id" @saved="onSaved" />
    </AppLayout>
</template>
