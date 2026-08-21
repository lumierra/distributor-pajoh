<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Plus, Trash2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { confirm } from '@/Composables/useConfirm';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { Textarea } from '@/Components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    schedules: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    salesUsers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    sales_id: props.filters.sales_id ? String(props.filters.sales_id) : ALL,
    pattern: props.filters.pattern || ALL,
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('sales-schedules.index'), {
            sales_id: filters.sales_id === ALL ? '' : filters.sales_id,
            pattern: filters.pattern === ALL ? '' : filters.pattern,
            customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const createOpen = ref(false);
const form = useForm({
    sales_id: '',
    customer_id: '',
    pattern: 'recurring',
    day_of_week: 1,
    visit_date: '',
    visit_time: '',
    start_date: '',
    end_date: '',
    notes: '',
    is_active: true,
});

function submit() {
    form.post(route('sales-schedules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
        },
    });
}

async function destroy(id) {
    if (!(await confirm({ title: 'Hapus schedule ini?', destructive: true }))) return;
    router.delete(route('sales-schedules.destroy', id), { preserveScroll: true });
}

const dayLabels = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

function fmtDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Sales Schedules" />

    <AppLayout>
        <PageHeader title="Jadwal Kunjungan" description="Jadwal kunjungan sales (recurring mingguan atau sekali jalan)." :icon="CalendarDays">
            <template #actions>
                <Button size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark" @click="createOpen = true">
                    <Plus class="size-4" /> Tambah
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-wrap items-center gap-2">
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[180px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.pattern">
                    <SelectTrigger class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Pattern" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Pattern</SelectItem>
                        <SelectItem value="recurring">Recurring</SelectItem>
                        <SelectItem value="one_time">Sekali Jalan</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.customer_id">
                    <SelectTrigger class="w-[200px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Customer" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Customer</SelectItem>
                        <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">Sales</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Pattern</TableHead>
                        <TableHead>Hari/Tgl</TableHead>
                        <TableHead>Jam</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="schedules.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground text-sm">Belum ada schedule.</TableCell>
                    </TableRow>
                    <TableRow v-for="s in schedules.data" :key="s.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4">{{ s.sales?.name ?? '—' }}</TableCell>
                        <TableCell>
                            <p>{{ s.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ s.customer?.code }}</p>
                        </TableCell>
                        <TableCell>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-muted/70 text-muted-foreground">
                                {{ s.pattern === 'recurring' ? 'Recurring' : 'Sekali Jalan' }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span v-if="s.pattern === 'recurring'">{{ dayLabels[s.day_of_week] ?? '—' }}</span>
                            <span v-else>{{ fmtDate(s.visit_date) }}</span>
                        </TableCell>
                        <TableCell class="text-xs">{{ s.visit_time ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium', s.is_active ? 'text-emerald-700' : 'text-muted-foreground']">
                                <span :class="['size-1.5 rounded-full', s.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50']" />
                                {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center pr-4">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroy(s.id)" />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="schedules.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="schedules" />
            </div>
        </section>

        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-[560px] p-0 overflow-hidden rounded-3xl gap-0">
                <DialogHeader class="px-6 pt-6 pb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                            <CalendarDays class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <DialogTitle class="text-base font-semibold tracking-tight">Tambah Jadwal Kunjungan</DialogTitle>
                        </div>
                    </div>
                </DialogHeader>
                <form class="space-y-4 px-6 pb-2" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <div class="space-y-1.5">
                            <Label>Sales</Label>
                            <Select v-model="form.sales_id">
                                <SelectTrigger class="w-full h-10 rounded-xl"><SelectValue placeholder="Pilih sales" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Customer</Label>
                            <Select v-model="form.customer_id">
                                <SelectTrigger class="w-full h-10 rounded-xl"><SelectValue placeholder="Pilih customer" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Pattern</Label>
                            <Select v-model="form.pattern">
                                <SelectTrigger class="w-full h-10 rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="recurring">Recurring</SelectItem>
                                    <SelectItem value="one_time">One-time</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="form.pattern === 'recurring'" class="space-y-1.5">
                            <Label>Hari</Label>
                            <Select v-model="form.day_of_week">
                                <SelectTrigger class="w-full h-10 rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, idx) in dayLabels.slice(1)" :key="idx" :value="idx + 1">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-else class="col-span-2 space-y-1.5">
                            <Label>Tanggal Kunjungan</Label>
                            <Input v-model="form.visit_date" type="date" class="w-full h-10 rounded-xl" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jam (opsional)</Label>
                            <Input v-model="form.visit_time" type="time" class="w-full h-10 rounded-xl" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Notes</Label>
                        <Textarea v-model="form.notes" rows="2" class="w-full rounded-xl" />
                    </div>
                </form>
                <DialogFooter class="px-6 py-4 gap-2">
                    <Button variant="outline" class="rounded-full" @click="createOpen = false">Batal</Button>
                    <Button class="rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="form.processing" @click="submit">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
