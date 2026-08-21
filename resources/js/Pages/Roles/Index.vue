<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Shield, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoleFormDialog from '@/Components/Roles/RoleFormDialog.vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
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
    roles: { type: Array, required: true },
});

const page = usePage();
const canCreate = computed(() => page.props.auth?.user?.is_superadmin === true);

const modalOpen = ref(false);
const editingRole = ref(null);

function openCreate() {
    editingRole.value = null;
    modalOpen.value = true;
}

function openEdit(r) {
    editingRole.value = r;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['roles'] });
}
</script>

<template>
    <Head title="Role Management" />

    <AppLayout>
        <PageHeader
            title="Role"
            description="Daftar role sistem & custom. Permission per menu diatur lewat tombol Permission."
            :icon="Shield"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Role
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow
                        class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent"
                    >
                        <TableHead class="pl-4">Role</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead class="text-right">User</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow
                        v-for="r in roles"
                        :key="r.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0"
                                >
                                    <ShieldCheck class="size-4" />
                                </div>
                                <div>
                                    <button
                                        type="button"
                                        class="text-left font-medium text-foreground hover:text-primary transition-colors block leading-tight"
                                        @click="openEdit(r)"
                                    >
                                        {{ r.name }}
                                    </button>
                                    <p
                                        class="text-[12px] text-muted-foreground font-mono leading-tight mt-0.5"
                                    >
                                        {{ r.code }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="r.is_system"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-muted/70 text-muted-foreground"
                            >
                                System
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-brand-light/70 text-brand-dark"
                            >
                                Custom
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ r.users_count }}</TableCell>
                        <TableCell class="py-3 px-4 text-center whitespace-nowrap">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton
                                        :icon="Pencil"
                                        label="Edit Detail"
                                        tone="blue"
                                        @click="openEdit(r)"
                                    />
                                    <ActionButton
                                        :icon="ShieldCheck"
                                        label="Edit Permission"
                                        as-child
                                        tone="brand"
                                    >
                                        <Link :href="route('roles.permissions.edit', r.id)">
                                            <ShieldCheck class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <RoleFormDialog v-model:open="modalOpen" :role="editingRole" @saved="onSaved" />
    </AppLayout>
</template>
