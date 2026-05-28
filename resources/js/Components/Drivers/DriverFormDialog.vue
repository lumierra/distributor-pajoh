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
        <DialogContent class="sm:max-w-[460px] p-0 overflow-hidden">
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

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Nama Lengkap *</Label>
                    <Input v-model="form.name" required class="h-9" />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">No. HP / WhatsApp</Label>
                    <Input v-model="form.whatsapp" class="h-9 font-mono" placeholder="cth: 08123456789" />
                    <p v-if="form.errors.whatsapp" class="text-xs text-destructive">{{ form.errors.whatsapp }}</p>
                </div>

                <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer">Status aktif</Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Driver nonaktif tidak muncul di dropdown DO.</p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>

                <!-- Field detail (NIK, alamat, SIM, kontak darurat, hire date, default vehicle, catatan)
                     di-hide sementara. Data DB tetap utuh untuk fitur lain (license check di DO, dll). -->
                <template v-if="false">
                    <Input v-model="form.nik" />
                    <Input v-model="form.phone" />
                    <Input v-model="form.city" />
                    <Textarea v-model="form.address" />
                    <Input v-model="form.license_no" />
                    <Input v-model="form.license_type" />
                    <Input v-model="form.license_expired_date" />
                    <Input v-model="form.emergency_contact_name" />
                    <Input v-model="form.emergency_contact_phone" />
                    <Input v-model="form.hire_date" />
                    <Input v-model="form.default_vehicle_id" />
                    <Textarea v-model="form.notes" />
                </template>
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
