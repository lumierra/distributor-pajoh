<script setup>
import { useForm } from '@inertiajs/vue3';
import { Building2, Factory, Loader2 } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = create mode, object = edit mode */
    supplier: { type: Object, default: null },
    categories: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.supplier);

const LEGAL_FORMS = ['PT', 'CV', 'UD', 'KOPERASI', 'PABRIK', 'LAINNYA'];

const form = useForm({
    name: '',
    legal_form: LEGAL_FORMS[0],
    npwp: '',
    nib: '',
    supplier_category_id: props.categories[0]?.id ?? null,
    phone: '',
    whatsapp: '',
    email: '',
    address: '',
    city: '',
    province: '',
    contact_person_name: '',
    contact_person_role: '',
    contact_person_phone: '',
    payment_term_days: null,
    default_lead_time_days: null,
    is_active: true,
    notes: '',
});

watch(
    () => [props.open, props.supplier?.id],
    ([open]) => {
        if (!open) return;
        if (props.supplier) {
            form.defaults({
                name: props.supplier.name ?? '',
                legal_form: props.supplier.legal_form ?? '',
                npwp: props.supplier.npwp ?? '',
                nib: props.supplier.nib ?? '',
                supplier_category_id: props.supplier.supplier_category_id ?? null,
                phone: props.supplier.phone ?? '',
                whatsapp: props.supplier.whatsapp ?? '',
                email: props.supplier.email ?? '',
                address: props.supplier.address ?? '',
                city: props.supplier.city ?? '',
                province: props.supplier.province ?? '',
                contact_person_name: props.supplier.contact_person_name ?? '',
                contact_person_role: props.supplier.contact_person_role ?? '',
                contact_person_phone: props.supplier.contact_person_phone ?? '',
                payment_term_days: props.supplier.payment_term_days ?? null,
                default_lead_time_days: props.supplier.default_lead_time_days ?? null,
                is_active: !!props.supplier.is_active,
                notes: props.supplier.notes ?? '',
            });
        } else {
            form.defaults({
                name: '',
                legal_form: LEGAL_FORMS[0],
                npwp: '',
                nib: '',
                supplier_category_id: props.categories[0]?.id ?? null,
                phone: '',
                whatsapp: '',
                email: '',
                address: '',
                city: '',
                province: '',
                contact_person_name: '',
                contact_person_role: '',
                contact_person_phone: '',
                payment_term_days: null,
                default_lead_time_days: null,
                is_active: true,
                notes: '',
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('suppliers.update', props.supplier.id), opts);
    } else {
        form.post(route('suppliers.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[560px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div
                        class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0"
                    >
                        <component :is="isEdit ? Building2 : Factory" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit Supplier — ${supplier.name}` : 'Tambah Supplier' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? `Code: ${supplier.code}. Rekening bank & dokumen di-edit di halaman detail.`
                                    : 'Buat supplier baru. Code auto-generate. Rekening bank & dokumen ditambah setelah save.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form
                class="px-6 pb-2 space-y-4 max-h-[65vh] overflow-y-auto"
                @submit.prevent="submit"
            >
                <!-- Identitas -->
                <div class="space-y-3">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Identitas
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Nama Supplier *</Label>
                            <Input v-model="form.name" required class="h-10 rounded-xl" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Bentuk Badan</Label>
                            <Select v-model="form.legal_form">
                                <SelectTrigger class="h-10 w-full rounded-xl">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="f in LEGAL_FORMS" :key="f" :value="f">
                                        {{ f }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kategori</Label>
                            <Select v-model="form.supplier_category_id">
                                <SelectTrigger class="h-10 w-full rounded-xl">
                                    <SelectValue placeholder="Pilih kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in categories" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <!-- NPWP & NIB di-hide sementara (next time mungkin dibutuhkan) -->
                        <template v-if="false">
                            <div class="space-y-1">
                                <Label class="text-xs font-medium">NPWP</Label>
                                <Input v-model="form.npwp" class="h-10 rounded-xl font-mono" />
                                <p v-if="form.errors.npwp" class="text-xs text-destructive">
                                    {{ form.errors.npwp }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs font-medium">NIB</Label>
                                <Input v-model="form.nib" class="h-10 rounded-xl font-mono" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Kontak -->
                <div class="space-y-3 pt-2 border-t border-foreground/5">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Kontak
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">No. HP / Phone</Label>
                            <Input v-model="form.phone" class="h-10 rounded-xl" />
                        </div>
                        <!-- WhatsApp & Email di-hide sementara -->
                        <template v-if="false">
                            <div class="space-y-1">
                                <Label class="text-xs font-medium">WhatsApp</Label>
                                <Input v-model="form.whatsapp" class="h-10 rounded-xl" />
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <Label class="text-xs font-medium">Email</Label>
                                <Input v-model="form.email" type="email" class="h-10 rounded-xl" />
                                <p v-if="form.errors.email" class="text-xs text-destructive">
                                    {{ form.errors.email }}
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Alamat (hidden sementara) -->
                <div v-if="false" class="space-y-3 pt-2 border-t border-foreground/5">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Alamat
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Alamat lengkap</Label>
                            <Textarea v-model="form.address" rows="2" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kota</Label>
                            <Input v-model="form.city" class="h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Provinsi</Label>
                            <Input v-model="form.province" class="h-10 rounded-xl" />
                        </div>
                    </div>
                </div>

                <!-- PIC (hidden sementara) -->
                <div v-if="false" class="space-y-3 pt-2 border-t border-foreground/5">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        PIC / Contact Person
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Nama</Label>
                            <Input v-model="form.contact_person_name" class="h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Jabatan</Label>
                            <Input v-model="form.contact_person_role" class="h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Phone PIC</Label>
                            <Input v-model="form.contact_person_phone" class="h-10 rounded-xl" />
                        </div>
                    </div>
                </div>

                <!-- Operasional -->
                <div class="space-y-3 pt-2 border-t border-foreground/5">
                    <p class="text-[12px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Operasional
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Payment Term (hari)</Label>
                            <Input
                                v-model="form.payment_term_days"
                                type="number"
                                min="0"
                                max="365"
                                class="h-10 rounded-xl"
                                placeholder="mis. 30"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Lead Time default (hari)</Label>
                            <Input
                                v-model="form.default_lead_time_days"
                                type="number"
                                min="0"
                                max="180"
                                class="h-10 rounded-xl"
                                placeholder="mis. 7"
                            />
                        </div>
                    </div>
                </div>

                <!-- Status & Notes -->
                <div class="space-y-3 pt-2 border-t border-foreground/5">
                    <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                        <div>
                            <Label class="text-xs font-medium block cursor-pointer mb-0">Status aktif</Label>
                            <p class="text-[12px] text-muted-foreground mt-0.5">
                                Supplier nonaktif tidak muncul di dropdown PO.
                            </p>
                        </div>
                        <Switch v-model="form.is_active" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="2" class="rounded-xl" placeholder="Catatan internal" />
                    </div>
                </div>
            </form>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Buat Supplier' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
