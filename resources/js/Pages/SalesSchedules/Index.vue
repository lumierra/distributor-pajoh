<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Plus, Trash2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
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

function destroy(id) {
    if (!confirm('Hapus schedule ini?')) return;
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
        <PageHeader title="Sales Schedules" description="Jadwal kunjungan sales (recurring weekly atau one-time)." :icon="CalendarDays">
            <template #actions>
                <Button size="default" @click="createOpen = true">
                    <Plus class="size-4" /> Tambah
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[180px] h-9"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.pattern">
                    <SelectTrigger class="w-[150px] h-9"><SelectValue placeholder="Pattern" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Pattern</SelectItem>
                        <SelectItem value="recurring">Recurring</SelectItem>
                        <SelectItem value="one_time">One-time</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.customer_id">
                    <SelectTrigger class="w-[200px] h-9"><SelectValue placeholder="Customer" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Customer</SelectItem>
                        <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Sales</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Pattern</TableHead>
                        <TableHead>Hari/Tgl</TableHead>
                        <TableHead>Jam</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="schedules.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground text-sm">Belum ada schedule.</TableCell>
                    </TableRow>
                    <TableRow v-for="s in schedules.data" :key="s.id">
                        <TableCell class="pl-4">{{ s.sales?.name ?? '—' }}</TableCell>
                        <TableCell>
                            <p>{{ s.customer?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ s.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs uppercase">{{ s.pattern }}</TableCell>
                        <TableCell>
                            <span v-if="s.pattern === 'recurring'">{{ dayLabels[s.day_of_week] ?? '—' }}</span>
                            <span v-else>{{ fmtDate(s.visit_date) }}</span>
                        </TableCell>
                        <TableCell class="text-xs">{{ s.visit_time ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="['text-[10px] px-1.5 py-0.5 rounded uppercase', s.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground']">
                                {{ s.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right pr-4">
                            <Button size="sm" variant="ghost" class="h-7 px-2 text-red-600" @click="destroy(s.id)">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="schedules.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="schedules" />
            </div>
        </section>

        <Dialog v-model:open="createOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Schedule</DialogTitle>
                </DialogHeader>
                <form class="space-y-3" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Sales</Label>
                            <Select v-model="form.sales_id">
                                <SelectTrigger><SelectValue placeholder="Pilih sales" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Customer</Label>
                            <Select v-model="form.customer_id">
                                <SelectTrigger><SelectValue placeholder="Pilih customer" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Pattern</Label>
                            <Select v-model="form.pattern">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="recurring">Recurring</SelectItem>
                                    <SelectItem value="one_time">One-time</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="form.pattern === 'recurring'">
                            <Label>Hari</Label>
                            <Select v-model="form.day_of_week">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, idx) in dayLabels.slice(1)" :key="idx" :value="idx + 1">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-else class="col-span-2">
                            <Label>Tanggal Kunjungan</Label>
                            <Input v-model="form.visit_date" type="date" />
                        </div>
                        <div>
                            <Label>Jam (opsional)</Label>
                            <Input v-model="form.visit_time" type="time" />
                        </div>
                    </div>
                    <div>
                        <Label>Notes</Label>
                        <Textarea v-model="form.notes" rows="2" />
                    </div>
                </form>
                <DialogFooter>
                    <Button variant="outline" @click="createOpen = false">Batal</Button>
                    <Button :disabled="form.processing" @click="submit">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
