<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CreditCard,
    Edit,
    Factory,
    FileText,
    Info,
    Landmark,
    Pencil,
    Plus,
    Star,
    Trash2,
    Upload,
    UserCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import BankAccountFormDialog from '@/Components/Suppliers/BankAccountFormDialog.vue';
import DocumentUploadDialog from '@/Components/Suppliers/DocumentUploadDialog.vue';
import SupplierFormDialog from '@/Components/Suppliers/SupplierFormDialog.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    categories: { type: Array, required: true },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

// Tabs
const tab = ref('info');
const tabs = computed(() => [
    { value: 'info', label: 'Info', icon: Info },
    {
        value: 'bank',
        label: `Bank (${props.supplier.bank_accounts?.length ?? 0})`,
        icon: Landmark,
    },
    {
        value: 'document',
        label: `Dokumen (${props.supplier.documents?.length ?? 0})`,
        icon: FileText,
    },
]);

// Edit supplier modal
const editOpen = ref(false);
function openEditSupplier() {
    editOpen.value = true;
}
function onSupplierSaved() {
    router.reload({ only: ['supplier'] });
}

// Bank account modal
const bankOpen = ref(false);
const editingBank = ref(null);
function openCreateBank() {
    editingBank.value = null;
    bankOpen.value = true;
}
function openEditBank(b) {
    editingBank.value = b;
    bankOpen.value = true;
}
function onBankSaved() {
    router.reload({ only: ['supplier'] });
}
function setDefaultBank(b) {
    useForm({}).post(route('supplier-bank-accounts.set-default', b.id), {
        preserveScroll: true,
    });
}

// Document modal
const docOpen = ref(false);
function openUploadDoc() {
    docOpen.value = true;
}
function onDocSaved() {
    router.reload({ only: ['supplier'] });
}

// Konfirmasi hapus (bank / dokumen) — pakai AlertDialog terkontrol, bukan window.confirm.
const deleteOpen = ref(false);
const deleteKind = ref(null); // 'bank' | 'document'
const deleteTarget = ref(null);

function askDestroyBank(b) {
    deleteKind.value = 'bank';
    deleteTarget.value = b;
    deleteOpen.value = true;
}

function askDestroyDoc(d) {
    deleteKind.value = 'document';
    deleteTarget.value = d;
    deleteOpen.value = true;
}

const deleteTitle = computed(() => {
    if (deleteKind.value === 'bank') {
        return `Hapus rekening ${deleteTarget.value?.bank_name} ${deleteTarget.value?.account_number}?`;
    }
    if (deleteKind.value === 'document') {
        return `Hapus dokumen "${deleteTarget.value?.title}"?`;
    }
    return '';
});

function confirmDestroy() {
    if (!deleteTarget.value) return;
    if (deleteKind.value === 'bank') {
        useForm({}).delete(route('supplier-bank-accounts.destroy', deleteTarget.value.id), {
            preserveScroll: true,
        });
    } else if (deleteKind.value === 'document') {
        useForm({}).delete(route('supplier-documents.destroy', deleteTarget.value.id), {
            preserveScroll: true,
        });
    }
}

// Helpers
function toggleActive() {
    useForm({}).post(route('suppliers.toggle-active', props.supplier.id), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatBytes(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function docExpiryBadge(d) {
    if (!d.expires_date) return null;
    const days = Math.ceil(
        (new Date(d.expires_date) - new Date()) / (1000 * 60 * 60 * 24),
    );
    if (days < 0)
        return {
            label: `Expired ${Math.abs(days)} hari lalu`,
            class: 'bg-red-50 text-red-700',
        };
    if (days <= 30)
        return {
            label: `Expired dalam ${days} hari`,
            class: 'bg-warning-soft text-amber-700',
        };
    return null;
}
</script>

<template>
    <Head :title="`Supplier: ${supplier.name}`" />

    <AppLayout>
        <PageHeader
            :title="supplier.name"
            :description="`${supplier.code} · ${supplier.category?.name ?? 'Tanpa kategori'}`"
            :icon="Factory"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('suppliers.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Supplier
                    </Link>
                </Button>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openEditSupplier"
                >
                    <Edit class="size-4" />
                    Edit Supplier
                </Button>
            </template>
        </PageHeader>

        <!-- Compact summary bar + tabs -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm mb-4 overflow-hidden">
            <div class="px-4 py-3 flex items-center gap-3 border-b border-foreground/5">
                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                    <Factory class="size-4" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ supplier.legal_form ? `${supplier.legal_form} · ` : '' }}{{ supplier.code }}
                    </p>
                    <h2 class="text-sm font-bold tracking-tight text-foreground truncate leading-tight">
                        {{ supplier.name }}
                    </h2>
                </div>
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                        supplier.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                    ]"
                >
                    <span
                        :class="[
                            'size-1.5 rounded-full',
                            supplier.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                        ]"
                    />
                    {{ supplier.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
                <Button v-if="canUpdate" variant="outline" size="sm" class="rounded-full" @click="toggleActive">
                    <component :is="supplier.is_active ? Ban : UserCheck" class="size-3.5" />
                    {{ supplier.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </div>
            <div class="px-4 py-2.5">
                <TabsPill v-model="tab" :tabs="tabs" tone="brand" />
            </div>
        </section>

        <!-- Tab: Info -->
        <section v-show="tab === 'info'" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                    <header class="border-b border-foreground/5 px-5 py-3">
                        <h3 class="text-sm font-semibold">Identitas</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Kode
                            </dt>
                            <dd class="font-mono mt-0.5">{{ supplier.code }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Bentuk Badan
                            </dt>
                            <dd class="mt-0.5">{{ supplier.legal_form || '—' }}</dd>
                        </div>
                        <!-- NPWP & NIB di-hide sementara -->
                        <template v-if="false">
                            <div>
                                <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                    NPWP
                                </dt>
                                <dd class="font-mono mt-0.5">{{ supplier.npwp || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                    NIB
                                </dt>
                                <dd class="font-mono mt-0.5">{{ supplier.nib || '—' }}</dd>
                            </div>
                        </template>
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Kategori
                            </dt>
                            <dd class="mt-0.5">{{ supplier.category?.name || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                    <header class="border-b border-foreground/5 px-5 py-3">
                        <h3 class="text-sm font-semibold">Kontak</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="sm:col-span-2">
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                No. HP / Phone
                            </dt>
                            <dd class="font-mono mt-0.5">{{ supplier.phone || '—' }}</dd>
                        </div>
                        <!-- WhatsApp & Email di-hide sementara -->
                        <template v-if="false">
                            <div>
                                <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                    WhatsApp
                                </dt>
                                <dd class="font-mono mt-0.5">{{ supplier.whatsapp || '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                    Email
                                </dt>
                                <dd class="mt-0.5">{{ supplier.email || '—' }}</dd>
                            </div>
                        </template>
                    </dl>
                </div>

                <!-- Alamat (hidden sementara) -->
                <div v-if="false" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                    <header class="border-b border-foreground/5 px-5 py-3">
                        <h3 class="text-sm font-semibold">Alamat</h3>
                    </header>
                    <div class="px-5 py-3 text-sm">
                        <p v-if="supplier.address" class="text-foreground whitespace-pre-line">
                            {{ supplier.address }}
                        </p>
                        <p v-else class="text-muted-foreground italic">Alamat belum diisi</p>
                        <p class="text-xs text-muted-foreground mt-2">
                            {{ [supplier.city, supplier.province, supplier.postal_code].filter(Boolean).join(', ') }}
                        </p>
                    </div>
                </div>

                <!-- PIC (hidden sementara) -->
                <div v-if="false" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                    <header class="border-b border-foreground/5 px-5 py-3">
                        <h3 class="text-sm font-semibold">PIC / Contact Person</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Nama
                            </dt>
                            <dd class="mt-0.5">{{ supplier.contact_person_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Jabatan
                            </dt>
                            <dd class="mt-0.5">{{ supplier.contact_person_role || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Phone PIC
                            </dt>
                            <dd class="font-mono mt-0.5">{{ supplier.contact_person_phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                                Email PIC
                            </dt>
                            <dd class="mt-0.5">{{ supplier.contact_person_email || '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm">
                <header class="border-b border-foreground/5 px-5 py-3 flex items-center gap-2">
                    <CreditCard class="size-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Operasional</h3>
                </header>
                <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                            Payment Term
                        </dt>
                        <dd class="mt-0.5">
                            <span v-if="supplier.payment_term_days">{{ supplier.payment_term_days }} hari</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                            Lead Time
                        </dt>
                        <dd class="mt-0.5">
                            <span v-if="supplier.default_lead_time_days">
                                {{ supplier.default_lead_time_days }} hari
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] uppercase tracking-wider text-muted-foreground font-semibold">
                            Dibuat
                        </dt>
                        <dd class="mt-0.5">{{ formatDate(supplier.created_at) }}</dd>
                    </div>
                </dl>
                <div v-if="supplier.notes" class="px-5 pb-3 text-sm text-muted-foreground whitespace-pre-line">
                    <p class="text-[12px] uppercase tracking-wider font-semibold mb-1">Catatan</p>
                    {{ supplier.notes }}
                </div>
            </div>
        </section>

        <!-- Tab: Bank Account -->
        <section v-show="tab === 'bank'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <header class="border-b border-foreground/5 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Rekening Bank</h3>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreateBank"
                >
                    <Plus class="size-4" />
                    Tambah Rekening
                </Button>
            </header>
            <ul v-if="supplier.bank_accounts?.length" class="divide-y divide-foreground/5">
                <li
                    v-for="b in supplier.bank_accounts"
                    :key="b.id"
                    class="px-5 py-3 flex items-center gap-3 hover:bg-foreground/2.5 transition-colors"
                >
                    <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                        <Landmark class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-foreground truncate">
                                {{ b.bank_name }}
                                <span v-if="b.branch" class="text-xs text-muted-foreground">— {{ b.branch }}</span>
                            </p>
                            <span
                                v-if="b.is_default"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-brand-light/70 text-brand-dark"
                            >
                                <Star class="size-3" /> Default
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground font-mono">
                            {{ b.account_number }} · a/n {{ b.account_holder }}
                        </p>
                    </div>
                    <ActionGroup v-if="canUpdate" class="rounded-full">
                        <ActionButton
                            v-if="! b.is_default"
                            :icon="Star"
                            label="Set default"
                            tone="amber"
                            @click="setDefaultBank(b)"
                        />
                        <ActionButton
                            :icon="Pencil"
                            label="Edit"
                            tone="blue"
                            @click="openEditBank(b)"
                        />
                        <ActionButton
                            :icon="Trash2"
                            label="Hapus"
                            tone="red"
                            @click="askDestroyBank(b)"
                        />
                    </ActionGroup>
                </li>
            </ul>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Landmark class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada rekening bank untuk supplier ini.
            </div>
        </section>

        <!-- Tab: Dokumen -->
        <section v-show="tab === 'document'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <header class="border-b border-foreground/5 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Dokumen</h3>
                <Button
                    v-if="canUpdate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openUploadDoc"
                >
                    <Upload class="size-4" />
                    Upload
                </Button>
            </header>
            <ul v-if="supplier.documents?.length" class="divide-y divide-foreground/5">
                <li
                    v-for="d in supplier.documents"
                    :key="d.id"
                    class="px-5 py-3 flex items-center gap-3 hover:bg-foreground/2.5 transition-colors"
                >
                    <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                        <FileText class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-medium text-foreground truncate">{{ d.title }}</p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-muted/70 text-muted-foreground">
                                {{ d.type.replace('_', ' ') }}
                            </span>
                            <span
                                v-if="docExpiryBadge(d)"
                                :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', docExpiryBadge(d).class]"
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
                    <ActionGroup class="rounded-full">
                        <ActionButton
                            :icon="FileText"
                            label="Lihat"
                            as-child
                            tone="brand"
                        >
                            <a
                                :href="route('supplier-documents.show', d.id)"
                                target="_blank"
                                rel="noopener"
                            >
                                <FileText class="w-4 h-4" />
                            </a>
                        </ActionButton>
                        <ActionButton
                            v-if="canUpdate"
                            :icon="Trash2"
                            label="Hapus"
                            tone="red"
                            @click="askDestroyDoc(d)"
                        />
                    </ActionGroup>
                </li>
            </ul>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <FileText class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada dokumen.
            </div>
        </section>

        <!-- Modals -->
        <SupplierFormDialog
            v-model:open="editOpen"
            :supplier="supplier"
            :categories="categories"
            @saved="onSupplierSaved"
        />
        <BankAccountFormDialog
            v-model:open="bankOpen"
            :supplier-id="supplier.id"
            :bank="editingBank"
            @saved="onBankSaved"
        />
        <DocumentUploadDialog
            v-model:open="docOpen"
            :supplier-id="supplier.id"
            @saved="onDocSaved"
        />

        <!-- Konfirmasi hapus (bank / dokumen) -->
        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ deleteTitle }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        Tindakan ini tidak bisa dibatalkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive/15 text-destructive hover:bg-destructive/25"
                        @click="confirmDestroy"
                    >
                        Hapus
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
