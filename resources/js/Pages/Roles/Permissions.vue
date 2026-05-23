<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckSquare, Loader2, ShieldCheck, Square } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
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
    role: { type: Object, required: true },
    menus: { type: Array, required: true },
    permissions: { type: Object, default: () => ({}) },
});

const actions = ['can_view', 'can_create', 'can_update', 'can_delete', 'can_approve', 'can_export'];

const rows = computed(() => {
    return props.menus.map((menu) => {
        const existing = props.permissions[menu.id];
        const row = {
            menu_id: menu.id,
            menu_code: menu.code,
            menu_label: menu.label,
            parent_id: menu.parent_id,
        };
        actions.forEach((a) => {
            row[a] = !!existing?.[a];
        });
        return row;
    });
});

const form = useForm({
    permissions: rows.value,
});

function setAction(idx, action, value) {
    const newRows = [...form.permissions];
    newRows[idx] = { ...newRows[idx], [action]: value };
    form.permissions = newRows;
}

function toggleAll(value) {
    form.permissions = form.permissions.map((row) => {
        const updated = { ...row };
        actions.forEach((a) => {
            updated[a] = value;
        });
        return updated;
    });
}

function submit() {
    form.put(route('roles.permissions.update', props.role.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Permission — ${role.name}`" />

    <AppLayout>
        <PageHeader
            :title="`Permission — ${role.name}`"
            description="Centang aksi yang diizinkan per menu. Menu tanpa centang `view` tidak akan tampil di top-bar untuk role ini."
            :icon="ShieldCheck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="lg">
                    <Link :href="route('roles.index')">
                        <ArrowLeft class="size-4" />
                        Kembali ke Role
                    </Link>
                </Button>
                <Button type="button" variant="outline" size="lg" @click="toggleAll(true)">
                    <CheckSquare class="size-4" />
                    Centang semua
                </Button>
                <Button type="button" variant="outline" size="lg" @click="toggleAll(false)">
                    <Square class="size-4" />
                    Hapus semua
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden">
                <header
                    class="border-b border-border/70 px-5 py-3.5 flex items-center justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold flex items-center gap-1.5">
                            Matrix Permission
                            <Badge variant="secondary">{{ role.code }}</Badge>
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ form.permissions.length }} menu · 6 jenis aksi.
                        </p>
                    </div>
                </header>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow
                                class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground bg-muted/30"
                            >
                                <TableHead class="pl-5 min-w-[18rem]">Menu</TableHead>
                                <TableHead
                                    v-for="a in actions"
                                    :key="a"
                                    class="text-center capitalize"
                                >
                                    {{ a.replace('can_', '') }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="text-sm">
                            <TableRow
                                v-for="(row, idx) in form.permissions"
                                :key="row.menu_id"
                                :class="[
                                    'transition-colors hover:bg-muted/30',
                                    row.parent_id === null && 'bg-muted/40 font-medium',
                                ]"
                            >
                                <TableCell
                                    :class="
                                        row.parent_id === null
                                            ? 'pl-5 font-semibold'
                                            : 'pl-10 text-muted-foreground'
                                    "
                                >
                                    <div class="flex flex-col leading-tight">
                                        <span>{{ row.menu_label }}</span>
                                        <span class="text-[10px] text-muted-foreground font-mono">
                                            {{ row.menu_code }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell v-for="a in actions" :key="a" class="text-center">
                                    <Checkbox
                                        :model-value="row[a]"
                                        @update:model-value="(v) => setAction(idx, a, !!v)"
                                    />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <footer
                    class="border-t border-border/70 px-5 py-3 flex justify-end gap-2 bg-muted/20"
                >
                    <Button type="button" variant="ghost" size="lg" as-child>
                        <Link :href="route('roles.index')">Batal</Link>
                    </Button>
                    <Button type="submit" size="lg" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Permission' }}
                    </Button>
                </footer>
            </section>
        </form>
    </AppLayout>
</template>
