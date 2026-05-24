<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CalendarCheck, CheckCircle2, Eye, PlayCircle } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    closings: { type: Array, required: true },
    previousYear: { type: Number, required: true },
    preCheck: { type: Object, default: () => ({ passed: false, issues: [] }) },
});

const executeOpen = ref(false);
const fiscalYear = ref(props.previousYear);
const processing = ref(false);

function executeClosing() {
    processing.value = true;
    router.post(route('year-end-closings.execute'), {
        fiscal_year: fiscalYear.value,
        confirm: 1,
    }, {
        preserveScroll: true,
        onSuccess: () => (executeOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Year-End Closing" />

    <AppLayout>
        <PageHeader title="Year-End Closing" description="Tutup tahun fiskal: carry-over docs, sequence reset, summary report." :icon="CalendarCheck">
            <template #actions>
                <Button @click="executeOpen = true" :disabled="!preCheck.passed">
                    <PlayCircle class="size-4" /> Execute Closing
                </Button>
            </template>
        </PageHeader>

        <section v-if="!preCheck.passed" class="mb-4 rounded-md ring-1 ring-amber-200 bg-amber-50 px-3 py-2.5">
            <div class="flex items-start gap-2 text-sm text-amber-900">
                <AlertTriangle class="size-4 mt-0.5" />
                <div>
                    <p class="font-semibold">Pre-check FY {{ previousYear }} belum lolos</p>
                    <ul class="text-xs mt-1 space-y-0.5">
                        <li v-for="issue in preCheck.issues" :key="issue.type">• {{ issue.message }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section v-else class="mb-4 rounded-md ring-1 ring-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800 flex items-center gap-2">
            <CheckCircle2 class="size-4" />
            <span>Pre-check FY {{ previousYear }} OK. Closing dapat dijalankan.</span>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">FY</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Closed At</TableHead>
                        <TableHead>By</TableHead>
                        <TableHead class="text-right">Revenue</TableHead>
                        <TableHead class="text-right">Margin</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="closings.length === 0">
                        <TableCell colspan="7" class="text-center py-16 text-muted-foreground text-sm">Belum ada closing.</TableCell>
                    </TableRow>
                    <TableRow v-for="c in closings" :key="c.id">
                        <TableCell class="pl-4 font-mono">{{ c.fiscal_year }}</TableCell>
                        <TableCell class="text-xs">{{ c.status }}</TableCell>
                        <TableCell class="text-xs">{{ fmt(c.closed_at) }}</TableCell>
                        <TableCell class="text-xs">{{ c.closer?.name ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(c.total_revenue) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(c.total_margin) }}</TableCell>
                        <TableCell class="text-right pr-4">
                            <Button as-child size="sm" variant="ghost" class="h-7 px-2">
                                <Link :href="route('year-end-closings.show', c.id)">
                                    <Eye class="size-3.5" />
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <Dialog v-model:open="executeOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Execute Year-End Closing</DialogTitle>
                    <DialogDescription>Tindakan ini akan flag carry-over, reset sequences, dan generate summary. Tidak dapat di-reverse.</DialogDescription>
                </DialogHeader>
                <div>
                    <Label>Fiscal Year</Label>
                    <Input v-model.number="fiscalYear" type="number" min="2020" max="2100" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="executeOpen = false">Batal</Button>
                    <Button :disabled="processing" @click="executeClosing">Execute</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
