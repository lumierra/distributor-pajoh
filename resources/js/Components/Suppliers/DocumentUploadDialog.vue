<script setup>
import { useForm } from '@inertiajs/vue3';
import { FileText, Loader2, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    supplierId: { type: Number, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const TYPES = ['NPWP', 'NIB', 'PKP', 'KONTRAK', 'SURAT_PERJANJIAN', 'LAINNYA'];

const form = useForm({
    type: 'KONTRAK',
    title: '',
    file: null,
    issued_date: '',
    expires_date: '',
    notes: '',
});

const fileInput = ref(null);
const fileName = computed(() => form.file?.name ?? '');
const fileSizeKb = computed(() =>
    form.file ? Math.round(form.file.size / 1024) : 0,
);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
        if (fileInput.value) fileInput.value.value = '';
    },
);

function pickFile(event) {
    const file = event.target.files?.[0];
    form.file = file ?? null;
}

function close() {
    emit('update:open', false);
}

function submit() {
    form.post(route('suppliers.documents.store', props.supplierId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200"
                    >
                        <Upload class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Upload Dokumen
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Maks 5 MB · PDF, JPG, PNG. Disimpan privat dengan signed URL.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tipe *</Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="h-9">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in TYPES" :key="t" :value="t">
                                    {{ t.replace('_', ' ') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Judul *</Label>
                        <Input v-model="form.title" required class="h-9" />
                        <p v-if="form.errors.title" class="text-xs text-destructive">
                            {{ form.errors.title }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">File *</Label>
                    <label
                        class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-border bg-muted/40 px-4 py-6 cursor-pointer hover:bg-muted/60 transition-colors"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            class="sr-only"
                            accept=".pdf,.jpg,.jpeg,.png"
                            @change="pickFile"
                        />
                        <FileText class="size-6 text-muted-foreground" />
                        <span v-if="!form.file" class="text-xs text-muted-foreground">
                            Klik untuk pilih file (PDF/JPG/PNG, maks 5 MB)
                        </span>
                        <span v-else class="text-xs font-medium text-foreground">
                            {{ fileName }} · {{ fileSizeKb }} KB
                        </span>
                    </label>
                    <p v-if="form.errors.file" class="text-xs text-destructive">
                        {{ form.errors.file }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tgl terbit</Label>
                        <Input v-model="form.issued_date" type="date" class="h-9" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tgl expired</Label>
                        <Input v-model="form.expires_date" type="date" class="h-9" />
                        <p v-if="form.errors.expires_date" class="text-xs text-destructive">
                            {{ form.errors.expires_date }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-medium">Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="form.processing || !form.file"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Mengunggah…' : 'Upload' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
