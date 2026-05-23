<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, UserCog } from '@lucide/vue';
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
    driver: { type: Object, default: null },
    vehicles: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.driver);

const LICENSE_TYPES = ['A', 'B1', 'B1_UMUM', 'B2', 'B2_UMUM', 'C'];

const form = useForm({
    name: '',
    nik: '',
    phone: '',
    whatsapp: '',
    address: '',
    city: '',
    license_no: '',
    license_type: null,
    license_expired_date: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    hire_date: '',
    default_vehicle_id: null,
    is_active: true,
    notes: '',
});

watch(
    () => [props.open, props.driver?.id],
    ([open]) => {
        if (!open) return;
        if (props.driver) {
            const d = props.driver;
            form.defaults({
                name: d.name ?? '',
                nik: d.nik ?? '',
                phone: d.phone ?? '',
                whatsapp: d.whatsapp ?? '',
                address: d.address ?? '',
                city: d.city ?? '',
                license_no: d.license_no ?? '',
                license_type: d.license_type ?? null,
                license_expired_date: d.license_expired_date ? String(d.license_expired_date).slice(0, 10) : '',
                emergency_contact_name: d.emergency_contact_name ?? '',
                emergency_contact_phone: d.emergency_contact_phone ?? '',
                hire_date: d.hire_date ? String(d.hire_date).slice(0, 10) : '',
                default_vehicle_id: d.default_vehicle_id ?? null,
                is_active: !!d.is_active,
                notes: d.notes ?? '',
            });
        } else {
            form.defaults({
                name: '', nik: '', phone: '', whatsapp: '', address: '', city: '',
                license_no: '', license_type: null, license_expired_date: '',
                emergency_contact_name: '', emergency_contact_phone: '',
                hire_date: '', default_vehicle_id: null, is_active: true, notes: '',
            });
        }
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('drivers.update', props.driver.id), opts);
    } else {
        form.post(route('drivers.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[600px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <UserCog class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? `Edit Driver — ${driver.name}` : 'Tambah Driver' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{ isEdit ? `Kode: ${driver.code}` : 'Kode auto-generate (DRV-xxxx).' }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto" @submit.prevent="submit">
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Identitas</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Nama Lengkap *</Label>
                            <Input v-model="form.name" required class="h-9" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">NIK</Label>
                            <Input v-model="form.nik" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Phone</Label>
                            <Input v-model="form.phone" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">WhatsApp</Label>
                            <Input v-model="form.whatsapp" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kota</Label>
                            <Input v-model="form.city" class="h-9" />
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Alamat</Label>
                            <Textarea v-model="form.address" rows="2" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-border/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">SIM</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">No. SIM</Label>
                            <Input v-model="form.license_no" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Tipe</Label>
                            <Select
                                :model-value="form.license_type ?? ''"
                                @update:model-value="(v) => (form.license_type = v || null)"
                            >
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="Pilih tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in LICENSE_TYPES" :key="t" :value="t">{{ t }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Berlaku Sampai</Label>
                            <Input v-model="form.license_expired_date" type="date" class="h-9" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-border/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Kontak Darurat</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Nama</Label>
                            <Input v-model="form.emergency_contact_name" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Phone</Label>
                            <Input v-model="form.emergency_contact_phone" class="h-9 font-mono" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-border/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Operasional</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Tanggal Masuk</Label>
                            <Input v-model="form.hire_date" type="date" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Default Vehicle</Label>
                            <Select
                                :model-value="form.default_vehicle_id ? String(form.default_vehicle_id) : ''"
                                @update:model-value="(v) => (form.default_vehicle_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger class="h-9">
                                    <SelectValue placeholder="Tanpa default" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="v in vehicles" :key="v.id" :value="String(v.id)">
                                        {{ v.plate_number }} ({{ v.code }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Catatan</Label>
                            <Textarea v-model="form.notes" rows="2" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer">Status aktif</Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Driver nonaktif tidak muncul di dropdown DO.</p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">Batal</Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Driver' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
