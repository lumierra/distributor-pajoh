<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Shield, ShieldPlus } from '@lucide/vue';
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
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    role: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.role);

const form = useForm({
    code: '',
    name: '',
    description: '',
    is_active: true,
    sort_order: 99,
});

watch(
    () => [props.open, props.role?.id],
    ([open]) => {
        if (!open) return;
        if (props.role) {
            form.defaults({
                code: props.role.code ?? '',
                name: props.role.name ?? '',
                description: props.role.description ?? '',
                is_active: !!props.role.is_active,
                sort_order: props.role.sort_order ?? 99,
            });
        } else {
            form.defaults({
                code: '',
                name: '',
                description: '',
                is_active: true,
                sort_order: 99,
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
        form.put(route('roles.update', props.role.id), opts);
    } else {
        form.post(route('roles.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div
                        class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0"
                    >
                        <component :is="isEdit ? Shield : ShieldPlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit Role — ${role.name}` : 'Tambah Role' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            {{
                                isEdit
                                    ? 'Update metadata role. Permission per menu di-edit terpisah.'
                                    : 'Buat role custom baru. Permission diatur setelah disimpan.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-6 pb-2 space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Code *</Label>
                        <Input
                            v-model="form.code"
                            required
                            :disabled="isEdit && role.is_system"
                            class="h-10 rounded-xl font-mono"
                        />
                        <p v-if="form.errors.code" class="text-xs text-destructive">
                            {{ form.errors.code }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Nama *</Label>
                        <Input v-model="form.name" required class="h-10 rounded-xl" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Deskripsi</Label>
                    <Textarea v-model="form.description" rows="2" class="rounded-xl" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_120px] gap-3.5">
                    <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                        <Label class="text-xs font-medium block cursor-pointer mb-0">Aktif</Label>
                        <Switch v-model="form.is_active" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Sort order</Label>
                        <Input v-model="form.sort_order" type="number" min="0" class="h-10 rounded-xl" />
                    </div>
                </div>
                <div
                    v-if="isEdit && role.is_system"
                    class="rounded-2xl bg-muted/40 px-4 py-3 text-xs flex items-start gap-2.5"
                >
                    <Shield class="size-4 text-muted-foreground shrink-0 mt-0.5" />
                    <p class="text-muted-foreground leading-relaxed">
                        System role: code &amp; permission tidak bisa diubah, hanya nama &amp; deskripsi.
                    </p>
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
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Role' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
