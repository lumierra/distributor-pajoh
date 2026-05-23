<script setup>
import { useForm } from '@inertiajs/vue3';
import { Camera, Loader2 } from '@lucide/vue';
import { watch } from 'vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    customerId: { type: Number, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({
    type: 'front',
    file: null,
    caption: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({ type: 'front', file: null, caption: '' });
        form.reset();
        form.clearErrors();
    },
);

function onFileChange(e) {
    form.file = e.target.files?.[0] ?? null;
}

function submit() {
    form.post(route('customers.photos.store', props.customerId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            emit('update:open', false);
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
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <Camera class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Upload Foto Outlet
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Max 3 MB. Format jpg/jpeg/png. Limit 10 foto per customer.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tipe *</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="h-9">
                            <SelectValue placeholder="Pilih tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="front">Tampak Depan</SelectItem>
                            <SelectItem value="interior">Interior</SelectItem>
                            <SelectItem value="owner">Pemilik</SelectItem>
                            <SelectItem value="other">Lainnya</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">File *</Label>
                    <Input type="file" accept="image/jpeg,image/png" class="h-9" @change="onFileChange" />
                    <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Caption (opsional)</Label>
                    <Input v-model="form.caption" class="h-9" />
                </div>
                <div v-if="form.progress" class="text-xs text-muted-foreground">
                    Uploading {{ form.progress.percentage }}%
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">
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
