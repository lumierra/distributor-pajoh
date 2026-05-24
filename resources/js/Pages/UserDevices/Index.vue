<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Smartphone, X } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
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

defineProps({
    devices: { type: Object, required: true },
    pending: { type: Array, default: () => [] },
});

const revokeOpen = ref(false);
const revokeTarget = ref(null);
const revokeReason = ref('');
const processing = ref(false);

const rejectOpen = ref(false);
const rejectTarget = ref(null);
const rejectReason = ref('');

function approve(id) {
    router.post(route('user-devices.approve-pending', id), {}, { preserveScroll: true });
}

function openReject(req) {
    rejectTarget.value = req;
    rejectReason.value = '';
    rejectOpen.value = true;
}

function submitReject() {
    processing.value = true;
    router.post(route('user-devices.reject-pending', rejectTarget.value.id), {
        rejection_reason: rejectReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function openRevoke(device) {
    revokeTarget.value = device;
    revokeReason.value = '';
    revokeOpen.value = true;
}

function submitRevoke() {
    processing.value = true;
    router.post(route('user-devices.revoke', revokeTarget.value.id), {
        revoke_reason: revokeReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (revokeOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID');
}

function statusBadge(s) {
    return {
        active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        inactive: 'bg-muted text-muted-foreground ring-border',
        revoked: 'bg-red-50 text-red-700 ring-red-200',
    }[s] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="User Devices" />

    <AppLayout>
        <PageHeader title="User Devices" description="Daftar device terdaftar + pending request approval." :icon="Smartphone" />

        <section v-if="pending.length > 0" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden mb-4">
            <h3 class="px-4 py-3 border-b text-sm font-semibold">Pending Approval ({{ pending.length }})</h3>
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">User</TableHead>
                        <TableHead>Device</TableHead>
                        <TableHead>OS</TableHead>
                        <TableHead>Requested IP</TableHead>
                        <TableHead>Requested At</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-for="req in pending" :key="req.id">
                        <TableCell class="pl-4">{{ req.user?.name }}</TableCell>
                        <TableCell>
                            <p>{{ req.device_name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ req.device_uuid }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ req.os ?? '—' }} {{ req.os_version }}</TableCell>
                        <TableCell class="font-mono text-xs">{{ req.requested_ip ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ fmt(req.requested_at) }}</TableCell>
                        <TableCell class="text-right pr-4 space-x-1">
                            <Button size="sm" variant="outline" class="h-7 px-2" @click="approve(req.id)">Approve</Button>
                            <Button size="sm" variant="outline" class="h-7 px-2 text-red-700" @click="openReject(req)">Reject</Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">User</TableHead>
                        <TableHead>Device</TableHead>
                        <TableHead>OS</TableHead>
                        <TableHead>Last Login</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="devices.data.length === 0">
                        <TableCell colspan="6" class="text-center py-12 text-muted-foreground text-sm">Belum ada device.</TableCell>
                    </TableRow>
                    <TableRow v-for="d in devices.data" :key="d.id">
                        <TableCell class="pl-4">{{ d.user?.name }}</TableCell>
                        <TableCell>
                            <p>{{ d.device_name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ d.device_uuid }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ d.os ?? '—' }} {{ d.os_version }}</TableCell>
                        <TableCell class="text-xs">{{ fmt(d.last_login_at) }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 capitalize', statusBadge(d.status)]">
                                {{ d.status }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right pr-4">
                            <Button v-if="d.status === 'active'" size="sm" variant="ghost" class="h-7 px-2 text-red-700" @click="openRevoke(d)">
                                <X class="size-3.5" /> Revoke
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div v-if="devices.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="devices" />
            </div>
        </section>

        <Dialog v-model:open="revokeOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Revoke Device</DialogTitle>
                    <DialogDescription>Device tidak akan bisa login lagi.</DialogDescription>
                </DialogHeader>
                <div>
                    <Label>Alasan</Label>
                    <Textarea v-model="revokeReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="revokeOpen = false">Batal</Button>
                    <Button variant="destructive" :disabled="processing || !revokeReason.trim()" @click="submitRevoke">Revoke</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rejectOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Device Request</DialogTitle>
                </DialogHeader>
                <div>
                    <Label>Alasan</Label>
                    <Textarea v-model="rejectReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectOpen = false">Batal</Button>
                    <Button variant="destructive" :disabled="processing || !rejectReason.trim()" @click="submitReject">Reject</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
