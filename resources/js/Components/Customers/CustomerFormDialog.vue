<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Store } from '@lucide/vue';
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
    customer: { type: Object, default: null },
    types: { type: Array, required: true },
    salesUsers: { type: Array, default: () => [] },
    canEditCreditLimit: { type: Boolean, default: false },
    canEditAssignedSales: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.customer);

const form = useForm({
    name: '',
    owner_name: '',
    customer_type_id: null,
    npwp: '',
    phone: '',
    whatsapp: '',
    email: '',
    address: '',
    city: '',
    province: '',
    postal_code: '',
    area: '',
    assigned_sales_id: null,
    credit_limit: 0,
    payment_term_days: 14,
    is_active: true,
    notes: '',
    tags: [],
});

watch(
    () => [props.open, props.customer?.id],
    ([open]) => {
        if (!open) return;
        if (props.customer) {
            const c = props.customer;
            form.defaults({
                name: c.name ?? '',
                owner_name: c.owner_name ?? '',
                customer_type_id: c.customer_type_id ?? null,
                npwp: c.npwp ?? '',
                phone: c.phone ?? '',
                whatsapp: c.whatsapp ?? '',
                email: c.email ?? '',
                address: c.address ?? '',
                city: c.city ?? '',
                province: c.province ?? '',
                postal_code: c.postal_code ?? '',
                area: c.area ?? '',
                assigned_sales_id: c.assigned_sales_id ?? null,
                credit_limit: Number(c.credit_limit ?? 0),
                payment_term_days: Number(c.payment_term_days ?? 0),
                is_active: !!c.is_active,
                notes: c.notes ?? '',
                tags: c.tags ?? [],
            });
        } else {
            form.defaults({
                name: '',
                owner_name: '',
                customer_type_id: null,
                npwp: '',
                phone: '',
                whatsapp: '',
                email: '',
                address: '',
                city: '',
                province: '',
                postal_code: '',
                area: '',
                assigned_sales_id: null,
                credit_limit: 0,
                payment_term_days: 14,
                is_active: true,
                notes: '',
                tags: [],
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
        form.put(route('customers.update', props.customer.id), opts);
    } else {
        form.post(route('customers.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[640px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <Store class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit Customer — ${customer.name}` : 'Tambah Customer' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? `Kode: ${customer.code}. Beberapa field dibatasi sesuai role.`
                                    : 'Kode auto-generate. Set sales penanggung jawab setelah simpan jika perlu.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-6 pb-2 space-y-4 max-h-[70vh] overflow-y-auto" @submit.prevent="submit">
                <!-- Identitas -->
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Identitas
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Nama Outlet *</Label>
                            <Input v-model="form.name" required class="h-10 rounded-xl" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Pemilik</Label>
                            <Input v-model="form.owner_name" class="h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Tipe</Label>
                            <Select
                                :model-value="form.customer_type_id ? String(form.customer_type_id) : ''"
                                @update:model-value="(v) => (form.customer_type_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger class="h-10 w-full rounded-xl">
                                    <SelectValue placeholder="Pilih tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in types" :key="t.id" :value="String(t.id)">
                                        {{ t.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <!-- NPWP di-hide sementara -->
                        <div v-if="false" class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">NPWP (opsional)</Label>
                            <Input v-model="form.npwp" class="h-10 rounded-xl font-mono" />
                        </div>
                    </div>
                </div>

                <!-- Kontak -->
                <div class="space-y-3 pt-3 border-t border-foreground/5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Kontak
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">No. WhatsApp</Label>
                            <Input v-model="form.whatsapp" class="h-10 rounded-xl font-mono" />
                        </div>
                        <!-- Phone & Email di-hide sementara -->
                        <template v-if="false">
                            <div class="space-y-1">
                                <Label class="text-xs font-medium">Phone</Label>
                                <Input v-model="form.phone" class="h-10 rounded-xl font-mono" />
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <Label class="text-xs font-medium">Email</Label>
                                <Input v-model="form.email" type="email" class="h-10 rounded-xl" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Alamat -->
                <div class="space-y-3 pt-3 border-t border-foreground/5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Alamat
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Alamat</Label>
                            <Textarea v-model="form.address" rows="2" class="rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kota</Label>
                            <Input v-model="form.city" class="h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Provinsi</Label>
                            <Input v-model="form.province" class="h-10 rounded-xl" />
                        </div>
                        <!-- Kode Pos di-hide sementara -->
                        <div v-if="false" class="space-y-1">
                            <Label class="text-xs font-medium">Kode Pos</Label>
                            <Input v-model="form.postal_code" class="h-10 rounded-xl font-mono" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Area Kerja</Label>
                            <Input v-model="form.area" class="h-10 rounded-xl" placeholder="Mis. Langsa" />
                        </div>
                    </div>
                </div>

                <!-- Sales & Finance -->
                <div class="space-y-3 pt-3 border-t border-foreground/5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Sales & Finance
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1 sm:col-span-2">
                            <Label class="text-xs font-medium">Sales Penanggung Jawab</Label>
                            <Select
                                :model-value="form.assigned_sales_id ? String(form.assigned_sales_id) : ''"
                                :disabled="isEdit && !canEditAssignedSales"
                                @update:model-value="(v) => (form.assigned_sales_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger class="h-10 w-full rounded-xl">
                                    <SelectValue placeholder="Tanpa sales" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">
                                        {{ s.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Payment Term (hari)</Label>
                            <Input
                                v-model="form.payment_term_days"
                                type="number"
                                min="0"
                                max="365"
                                class="h-10 rounded-xl font-mono"
                            />
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer mb-0">Status aktif</Label>
                        <p class="text-[12px] text-muted-foreground mt-0.5">
                            Outlet nonaktif tidak muncul di dropdown SO.
                        </p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>

                <!-- Catatan -->
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" class="rounded-xl" />
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
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Customer' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
