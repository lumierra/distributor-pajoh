<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Truck } from '@lucide/vue';
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
    vehicle: { type: Object, default: null },
    types: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.vehicle);

const form = useForm({
    plate_number: '',
    type: 'truck',
    brand: '',
    model: '',
    year: null,
    color: '',
    capacity_kg: null,
    capacity_kubik: null,
    last_service_date: '',
    next_service_date: '',
    odometer_km: null,
    is_active: true,
    notes: '',
});

watch(
    () => [props.open, props.vehicle?.id],
    ([open]) => {
        if (!open) return;
        if (props.vehicle) {
            const v = props.vehicle;
            form.defaults({
                plate_number: v.plate_number ?? '',
                type: v.type ?? 'truck',
                brand: v.brand ?? '',
                model: v.model ?? '',
                year: v.year ?? null,
                color: v.color ?? '',
                capacity_kg: v.capacity_kg ?? null,
                capacity_kubik: v.capacity_kubik ?? null,
                last_service_date: v.last_service_date ? String(v.last_service_date).slice(0, 10) : '',
                next_service_date: v.next_service_date ? String(v.next_service_date).slice(0, 10) : '',
                odometer_km: v.odometer_km ?? null,
                is_active: !!v.is_active,
                notes: v.notes ?? '',
            });
        } else {
            form.defaults({
                plate_number: '', type: 'truck', brand: '', model: '', year: null, color: '',
                capacity_kg: null, capacity_kubik: null,
                last_service_date: '', next_service_date: '', odometer_km: null,
                is_active: true, notes: '',
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
        form.put(route('vehicles.update', props.vehicle.id), opts);
    } else {
        form.post(route('vehicles.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[600px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <Truck class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            {{ isEdit ? `Edit Vehicle — ${vehicle.plate_number}` : 'Tambah Vehicle' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Plat format Indonesia: <span class="font-mono">BL 9195 XX</span> (huruf-spasi-angka-spasi-huruf).
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto" @submit.prevent="submit">
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Identitas</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">No. Plat *</Label>
                            <Input v-model="form.plate_number" required class="h-9 font-mono uppercase" placeholder="BL 9195 XX" />
                            <p v-if="form.errors.plate_number" class="text-xs text-destructive">{{ form.errors.plate_number }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Tipe *</Label>
                            <Select v-model="form.type">
                                <SelectTrigger class="h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in types" :key="t" :value="t">{{ t }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Brand</Label>
                            <Input v-model="form.brand" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Model</Label>
                            <Input v-model="form.model" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Tahun</Label>
                            <Input v-model="form.year" type="number" min="1990" max="2100" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Warna</Label>
                            <Input v-model="form.color" class="h-9" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-border/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Kapasitas</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kapasitas (kg)</Label>
                            <Input v-model="form.capacity_kg" type="number" step="0.01" min="0" class="h-9 font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kapasitas (m³)</Label>
                            <Input v-model="form.capacity_kubik" type="number" step="0.001" min="0" class="h-9 font-mono" />
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-border/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Service</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Service Terakhir</Label>
                            <Input v-model="form.last_service_date" type="date" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Service Berikut</Label>
                            <Input v-model="form.next_service_date" type="date" class="h-9" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Odometer (km)</Label>
                            <Input v-model="form.odometer_km" type="number" min="0" class="h-9 font-mono" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-md bg-muted/40 ring-1 ring-foreground/5 px-3.5 py-2.5">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer">Status aktif</Label>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Vehicle nonaktif tidak muncul di dropdown DO.</p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" />
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
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Vehicle' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
