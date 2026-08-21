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
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <Camera class="size-5" />
                    </div>
                    <div class="pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            Upload Foto Outlet
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Max 3 MB. Format jpg/jpeg/png. Limit 10 foto per customer.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-6 pb-2 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Tipe *</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="h-10 w-full rounded-xl">
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
                    <Input type="file" accept="image/jpeg,image/png" class="h-10 rounded-xl" @change="onFileChange" />
                    <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Caption (opsional)</Label>
                    <Input v-model="form.caption" class="h-10 rounded-xl" />
                </div>
                <div v-if="form.progress" class="text-xs text-muted-foreground">
                    Uploading {{ form.progress.percentage }}%
                </div>
            </form>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="$emit('update:open', false)">
                    Batal
                </Button>
                <Button
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
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
