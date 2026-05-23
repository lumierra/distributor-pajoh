<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2, Menu as MenuIcon, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Badge } from '@/Components/ui/badge';
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
import { Switch } from '@/Components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    menus: { type: Array, required: true },
});

const editing = ref(null);

const form = useForm({
    label: '',
    icon: '',
    order: 0,
    is_active: true,
    description: '',
});

function openEdit(menu) {
    editing.value = menu;
    form.label = menu.label;
    form.icon = menu.icon ?? '';
    form.order = menu.order;
    form.is_active = !!menu.is_active;
    form.description = menu.description ?? '';
}

function submit() {
    form.put(route('menus.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

const isOpen = computed({
    get: () => !!editing.value,
    set: (v) => {
        if (!v) editing.value = null;
    },
});
</script>

<template>
    <Head title="Menu Builder" />

    <AppLayout>
        <PageHeader
            title="Menu Builder"
            description="Edit metadata menu sistem (label, icon, order, status). Menu hanya bisa ditambah lewat migration."
            :icon="MenuIcon"
        />

        <section class="rounded-lg ring-1 ring-foreground/10 bg-card overflow-hidden shadow-xs">
            <Table>
                <TableHeader>
                    <TableRow
                        class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground bg-muted/30"
                    >
                        <TableHead class="pl-5 min-w-[16rem]">Menu</TableHead>
                        <TableHead>Route</TableHead>
                        <TableHead class="text-center">Order</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-center">Tipe</TableHead>
                        <TableHead class="w-0 pr-5" />
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow
                        v-for="m in menus"
                        :key="m.id"
                        :class="[
                            'group transition-colors hover:bg-muted/30',
                            m.parent_id === null && 'bg-muted/40',
                        ]"
                    >
                        <TableCell
                            :class="
                                m.parent_id === null
                                    ? 'pl-5 py-2.5 font-semibold'
                                    : 'pl-10 py-2.5 text-muted-foreground'
                            "
                        >
                            <div class="flex flex-col leading-tight">
                                <span>{{ m.label }}</span>
                                <span class="text-[10px] text-muted-foreground font-mono">
                                    {{ m.code }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="text-xs font-mono text-muted-foreground">
                            {{ m.route ?? '—' }}
                        </TableCell>
                        <TableCell class="text-center tabular-nums">{{ m.order }}</TableCell>
                        <TableCell class="text-center">
                            <Badge :variant="m.is_active ? 'default' : 'secondary'">
                                {{ m.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-center">
                            <Badge :variant="m.is_system ? 'secondary' : 'outline'">
                                {{ m.is_system ? 'System' : 'Custom' }}
                            </Badge>
                        </TableCell>
                        <TableCell class="pr-5">
                            <Button variant="ghost" size="sm" @click="openEdit(m)">
                                <Pencil class="size-3.5" />
                                Edit
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Edit Menu</DialogTitle>
                    <DialogDescription v-if="editing">
                        <span class="font-mono">{{ editing.code }}</span>
                        · {{ editing.route ?? 'no route' }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Label</Label>
                        <Input v-model="form.label" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Icon (Lucide name)</Label>
                        <Input v-model="form.icon" placeholder="LayoutDashboard, Package, …" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Order</Label>
                            <Input v-model="form.order" type="number" min="0" required />
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md ring-1 ring-foreground/10 bg-background px-3.5 py-2"
                        >
                            <Label class="cursor-pointer">Aktif</Label>
                            <Switch v-model="form.is_active" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Deskripsi</Label>
                        <Textarea v-model="form.description" rows="2" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" size="lg" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
