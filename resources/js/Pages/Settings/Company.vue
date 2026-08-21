<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, FileText, Image, Lock, Mail, Save } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
    invoiceText: { type: Object, default: () => ({}) },
    assets: { type: Object, default: null },
    can: { type: Object, default: () => ({ update: false, manageAssets: false }) },
});

const tab = ref('identitas');

const tabs = computed(() => {
    const list = [
        { value: 'identitas', label: 'Identitas', icon: Building2 },
        { value: 'kontak', label: 'Kontak', icon: Mail },
    ];

    if (props.can.manageAssets) {
        list.push({ value: 'asset', label: 'Asset', icon: Image });
    }

    list.push({ value: 'faktur', label: 'Teks Faktur', icon: FileText });

    return list;
});

// ── Tab Identitas + Kontak (group `company`) ──────────────────────────
// Satu form untuk dua tab: field-nya satu group setting, jadi sekali simpan.
const profileForm = useForm({
    name: props.profile.name ?? '',
    legal_form: props.profile.legal_form ?? '',
    npwp: props.profile.npwp ?? '',
    nib: props.profile.nib ?? '',
    address: props.profile.address ?? '',
    city: props.profile.city ?? '',
    province: props.profile.province ?? '',
    postal_code: props.profile.postal_code ?? '',
    phone: props.profile.phone ?? '',
    whatsapp: props.profile.whatsapp ?? '',
    email: props.profile.email ?? '',
    website: props.profile.website ?? '',
});

function saveProfile() {
    profileForm.post(route('settings.company.profile.update'), { preserveScroll: true });
}

// ── Tab Teks Faktur (group `company.invoice_text`) ────────────────────
const invoiceForm = useForm({
    header_text: props.invoiceText.header_text ?? '',
    footer_text: props.invoiceText.footer_text ?? '',
    terms_and_conditions: props.invoiceText.terms_and_conditions ?? '',
    payment_instruction: props.invoiceText.payment_instruction ?? '',
});

function saveInvoiceText() {
    invoiceForm.post(route('settings.company.invoice-text.update'), { preserveScroll: true });
}

/**
 * Faktur dicetak ke dot-matrix continuous form yang lebarnya terbatas,
 * jadi baris melebihi ~80 karakter berisiko terpotong saat cetak.
 */
const MAX_LINE_CHARS = 80;

function longestLine(text) {
    return (text ?? '').split('\n').reduce((max, line) => Math.max(max, line.length), 0);
}

// ── Tab Asset (group `company.assets`) ────────────────────────────────
const assetForm = useForm({
    logo: null,
    signature: null,
    stamp: null,
    remove_logo: false,
    remove_signature: false,
    remove_stamp: false,
    show_signature_on_invoice: props.assets?.show_signature_on_invoice ?? true,
    show_stamp_on_invoice: props.assets?.show_stamp_on_invoice ?? true,
});

// Preview lokal hasil pilih file, sebelum benar-benar di-upload.
const previews = ref({ logo: null, signature: null, stamp: null });

function pickFile(field, event) {
    const file = event.target.files?.[0] ?? null;
    assetForm[field] = file;
    assetForm[`remove_${field}`] = false;
    previews.value[field] = file ? URL.createObjectURL(file) : null;
}

function markRemoved(field) {
    assetForm[field] = null;
    assetForm[`remove_${field}`] = true;
    previews.value[field] = null;
}

function currentPreview(field) {
    if (previews.value[field]) {
        return previews.value[field];
    }

    if (assetForm[`remove_${field}`]) {
        return null;
    }

    return props.assets?.[`${field}_url`] ?? null;
}

function saveAssets() {
    assetForm.post(route('settings.company.assets.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            assetForm.logo = null;
            assetForm.signature = null;
            assetForm.stamp = null;
            assetForm.remove_logo = false;
            assetForm.remove_signature = false;
            assetForm.remove_stamp = false;
            previews.value = { logo: null, signature: null, stamp: null };
        },
    });
}

const assetFields = [
    {
        field: 'logo',
        label: 'Logo',
        hint: 'PNG/JPG/WEBP, maks 2 MB. Dipakai di header aplikasi & faktur.',
        sensitive: false,
        toggle: null,
    },
    {
        field: 'signature',
        label: 'Tanda Tangan Owner',
        hint: 'PNG background transparan, maks 1 MB.',
        sensitive: true,
        toggle: 'show_signature_on_invoice',
    },
    {
        field: 'stamp',
        label: 'Stempel CV',
        hint: 'PNG background transparan, maks 1 MB.',
        sensitive: true,
        toggle: 'show_stamp_on_invoice',
    },
];
</script>

<template>
    <Head title="Profil Perusahaan" />

    <AppLayout>
        <PageHeader
            title="Profil Perusahaan"
            description="Identitas CV, kontak, asset, dan teks yang tercetak di faktur."
            :icon="Building2"
        />

        <div class="mb-4">
            <TabsPill v-model="tab" :tabs="tabs" />
        </div>

        <!-- ─────────────── Tab: Identitas ─────────────── -->
        <form
            v-show="tab === 'identitas'"
            class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4"
            @submit.prevent="saveProfile"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1 sm:col-span-2">
                    <Label>Nama CV <span class="text-destructive">*</span></Label>
                    <Input v-model="profileForm.name" :disabled="!can.update" />
                    <p v-if="profileForm.errors.name" class="text-[12px] text-destructive mt-1">
                        {{ profileForm.errors.name }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label>Bentuk Badan Usaha</Label>
                    <Input v-model="profileForm.legal_form" placeholder="CV" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>NPWP</Label>
                    <Input v-model="profileForm.npwp" placeholder="00.000.000.0-000.000" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>NIB / No Izin Usaha</Label>
                    <Input v-model="profileForm.nib" :disabled="!can.update" />
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <Label>Alamat Lengkap</Label>
                    <Textarea v-model="profileForm.address" rows="3" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>Kota</Label>
                    <Input v-model="profileForm.city" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>Provinsi</Label>
                    <Input v-model="profileForm.province" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>Kode Pos</Label>
                    <Input v-model="profileForm.postal_code" :disabled="!can.update" />
                </div>
            </div>

            <div v-if="can.update" class="pt-1 flex items-center gap-2">
                <Button type="submit" :disabled="profileForm.processing">
                    <Save class="size-4" />
                    Simpan Perubahan
                </Button>
                <span v-if="profileForm.isDirty" class="text-[12px] text-amber-600">Ada perubahan belum disimpan.</span>
            </div>
        </form>

        <!-- ─────────────── Tab: Kontak ─────────────── -->
        <form
            v-show="tab === 'kontak'"
            class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4"
            @submit.prevent="saveProfile"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <Label>Telepon</Label>
                    <Input v-model="profileForm.phone" placeholder="0641-123456" :disabled="!can.update" />
                </div>
                <div class="space-y-1">
                    <Label>WhatsApp</Label>
                    <Input v-model="profileForm.whatsapp" placeholder="6281234567890" :disabled="!can.update" />
                    <p class="text-[12px] text-muted-foreground mt-1">Format internasional tanpa tanda +.</p>
                </div>
                <div class="space-y-1">
                    <Label>Email</Label>
                    <Input v-model="profileForm.email" type="email" :disabled="!can.update" />
                    <p v-if="profileForm.errors.email" class="text-[12px] text-destructive mt-1">
                        {{ profileForm.errors.email }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label>Website</Label>
                    <Input v-model="profileForm.website" placeholder="https://" :disabled="!can.update" />
                </div>
            </div>

            <div v-if="can.update" class="pt-1 flex items-center gap-2">
                <Button type="submit" :disabled="profileForm.processing">
                    <Save class="size-4" />
                    Simpan Perubahan
                </Button>
                <span v-if="profileForm.isDirty" class="text-[12px] text-amber-600">Ada perubahan belum disimpan.</span>
            </div>
        </form>

        <!-- ─────────────── Tab: Asset (superadmin) ─────────────── -->
        <form
            v-if="can.manageAssets"
            v-show="tab === 'asset'"
            class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-5"
            @submit.prevent="saveAssets"
        >
            <div
                v-for="a in assetFields"
                :key="a.field"
                class="flex flex-col sm:flex-row gap-4 pb-5 border-b border-border/70 last:border-0 last:pb-0"
            >
                <div
                    class="w-[140px] h-[90px] shrink-0 rounded-md ring-1 ring-foreground/10 bg-muted/30 flex items-center justify-center overflow-hidden"
                >
                    <img
                        v-if="currentPreview(a.field)"
                        :src="currentPreview(a.field)"
                        :alt="a.label"
                        class="max-w-full max-h-full object-contain"
                    >
                    <span v-else class="text-[12px] text-muted-foreground px-2 text-center">belum di-upload</span>
                </div>

                <div class="flex-1 space-y-2">
                    <Label class="flex items-center gap-1.5">
                        {{ a.label }}
                        <Lock v-if="a.sensitive" class="size-3 text-amber-600" />
                    </Label>

                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="h-9 max-w-[260px]"
                            @change="pickFile(a.field, $event)"
                        />
                        <Button
                            v-if="currentPreview(a.field)"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="markRemoved(a.field)"
                        >
                            Hapus
                        </Button>
                    </div>

                    <p class="text-[12px] text-muted-foreground">{{ a.hint }}</p>
                    <p v-if="assetForm.errors[a.field]" class="text-[12px] text-destructive">
                        {{ assetForm.errors[a.field] }}
                    </p>

                    <div v-if="a.toggle" class="flex items-center gap-2 pt-1">
                        <Switch :id="a.toggle" v-model="assetForm[a.toggle]" />
                        <Label :for="a.toggle" class="text-xs font-normal cursor-pointer mb-0">
                            Tampilkan di faktur PDF
                        </Label>
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <Button type="submit" :disabled="assetForm.processing">
                    <Save class="size-4" />
                    Simpan Asset
                </Button>
            </div>
        </form>

        <!-- ─────────────── Tab: Teks Faktur ─────────────── -->
        <form
            v-show="tab === 'faktur'"
            class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-4"
            @submit.prevent="saveInvoiceText"
        >
            <p class="text-[12px] text-muted-foreground">
                Teks dicetak apa adanya ke faktur dot-matrix — tanpa format tebal/miring.
                Jaga tiap baris di bawah {{ MAX_LINE_CHARS }} karakter agar tidak terpotong saat cetak.
            </p>

            <div class="space-y-1">
                <Label>Header Tambahan</Label>
                <Textarea v-model="invoiceForm.header_text" rows="2" class="font-mono text-xs" :disabled="!can.update" />
                <p class="text-[12px] mt-1" :class="longestLine(invoiceForm.header_text) > MAX_LINE_CHARS ? 'text-destructive' : 'text-muted-foreground'">
                    Baris terpanjang: {{ longestLine(invoiceForm.header_text) }} / {{ MAX_LINE_CHARS }} karakter
                </p>
            </div>

            <div class="space-y-1">
                <Label>Kalimat Sebelum List Rekening</Label>
                <Textarea v-model="invoiceForm.payment_instruction" rows="2" class="font-mono text-xs" :disabled="!can.update" />
                <p class="text-[12px] mt-1" :class="longestLine(invoiceForm.payment_instruction) > MAX_LINE_CHARS ? 'text-destructive' : 'text-muted-foreground'">
                    Baris terpanjang: {{ longestLine(invoiceForm.payment_instruction) }} / {{ MAX_LINE_CHARS }} karakter
                </p>
            </div>

            <div class="space-y-1">
                <Label>Footer Faktur</Label>
                <Textarea v-model="invoiceForm.footer_text" rows="3" class="font-mono text-xs" :disabled="!can.update" />
                <p class="text-[12px] mt-1" :class="longestLine(invoiceForm.footer_text) > MAX_LINE_CHARS ? 'text-destructive' : 'text-muted-foreground'">
                    Baris terpanjang: {{ longestLine(invoiceForm.footer_text) }} / {{ MAX_LINE_CHARS }} karakter
                </p>
            </div>

            <div class="space-y-1">
                <Label>Syarat &amp; Ketentuan <span class="text-muted-foreground font-normal">(opsional)</span></Label>
                <Textarea v-model="invoiceForm.terms_and_conditions" rows="4" class="font-mono text-xs" :disabled="!can.update" />
                <p class="text-[12px] mt-1" :class="longestLine(invoiceForm.terms_and_conditions) > MAX_LINE_CHARS ? 'text-destructive' : 'text-muted-foreground'">
                    Baris terpanjang: {{ longestLine(invoiceForm.terms_and_conditions) }} / {{ MAX_LINE_CHARS }} karakter
                </p>
            </div>

            <div v-if="can.update" class="pt-1 flex items-center gap-2">
                <Button type="submit" :disabled="invoiceForm.processing">
                    <Save class="size-4" />
                    Simpan Teks Faktur
                </Button>
                <span v-if="invoiceForm.isDirty" class="text-[12px] text-amber-600">Ada perubahan belum disimpan.</span>
            </div>
        </form>
    </AppLayout>
</template>
