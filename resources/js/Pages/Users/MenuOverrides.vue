<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
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

const props = defineProps({
    user: { type: Object, required: true },
    menus: { type: Array, required: true },
    overrides: { type: Object, default: () => ({}) },
});

const actions = ['can_view', 'can_create', 'can_update', 'can_delete', 'can_approve', 'can_export'];

const rows = computed(() => {
    return props.menus.map((menu) => {
        const existing = props.overrides[menu.id];
        const row = {
            menu_id: menu.id,
            menu_code: menu.code,
            menu_label: menu.label,
            parent_id: menu.parent_id,
            note: existing?.note ?? '',
        };
        actions.forEach((a) => {
            row[a] = existing?.[a] ?? null;
        });
        return row;
    });
});

const form = useForm({
    overrides: rows.value,
});

function toggle(idx, action, value) {
    const newOverrides = [...form.overrides];
    newOverrides[idx] = { ...newOverrides[idx], [action]: value };
    form.overrides = newOverrides;
}

function submit() {
    form.put(route('users.menu-overrides.update', props.user.id), { preserveScroll: true });
}

function labelOf(value) {
    if (value === true) return 'Izinkan';
    if (value === false) return 'Tolak';
    return 'Default';
}

function selectValueOf(value) {
    if (value === null || value === undefined) return 'inherit';
    return value ? 'grant' : 'deny';
}

function fromSelectValue(v) {
    if (v === 'inherit') return null;
    return v === 'grant';
}

/**
 * Mapping kolom action (DB column → label Bahasa Indonesia di header tabel).
 */
const ACTION_LABELS = {
    can_view: 'Lihat',
    can_create: 'Tambah',
    can_update: 'Ubah',
    can_delete: 'Hapus',
    can_approve: 'Setujui',
    can_export: 'Ekspor',
};

function actionLabel(action) {
    return ACTION_LABELS[action] ?? action.replace('can_', '');
}
</script>

<template>
    <Head :title="`Menu Override — ${user.name}`" />

    <AppLayout>
        <PageHeader
            :title="`Menu Override — ${user.name}`"
            description="Override izin per menu di luar default role. Pilih `Default` untuk ikut role."
            :icon="ShieldCheck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="lg">
                    <Link :href="route('users.index')">
                        <ArrowLeft class="size-4" />
                        Kembali ke daftar
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-lg ring-1 ring-foreground/10 bg-card shadow-xs overflow-hidden">
                <header class="border-b border-border/70 px-5 py-3.5">
                    <h2 class="text-sm font-semibold flex items-center gap-1.5">
                        Matriks Override
                        <Badge variant="secondary" class="capitalize">
                            Role: {{ user.role?.name }}
                        </Badge>
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Pilih <em>Izinkan</em> untuk menambah akses, <em>Tolak</em> untuk mencabut,
                        <em>Default</em> untuk ikuti role.
                    </p>
                </header>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow
                                class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground bg-muted/30"
                            >
                                <TableHead class="pl-5 min-w-[16rem]">Menu</TableHead>
                                <TableHead
                                    v-for="a in actions"
                                    :key="a"
                                    class="text-center"
                                >
                                    {{ actionLabel(a) }}
                                </TableHead>
                                <TableHead class="min-w-[14rem] pr-5">Catatan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="text-sm">
                            <TableRow
                                v-for="(row, idx) in form.overrides"
                                :key="row.menu_id"
                                :class="[
                                    'transition-colors hover:bg-muted/30',
                                    row.parent_id === null && 'bg-muted/40',
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
                                        <span class="text-[11px] text-muted-foreground font-mono">
                                            {{ row.menu_code }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell v-for="a in actions" :key="a" class="text-center">
                                    <Select
                                        :model-value="selectValueOf(row[a])"
                                        @update:model-value="(v) => toggle(idx, a, fromSelectValue(v))"
                                    >
                                        <SelectTrigger size="sm" class="h-7 w-24 text-[12px]">
                                            <SelectValue>{{ labelOf(row[a]) }}</SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="inherit">Default</SelectItem>
                                            <SelectItem value="grant">Izinkan</SelectItem>
                                            <SelectItem value="deny">Tolak</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell class="pr-5">
                                    <Textarea
                                        v-model="row.note"
                                        rows="1"
                                        class="text-xs min-h-7"
                                        placeholder="Alasan (opsional)"
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
                        <Link :href="route('users.index')">Batal</Link>
                    </Button>
                    <Button type="submit" size="lg" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Override' }}
                    </Button>
                </footer>
            </section>
        </form>
    </AppLayout>
</template>
