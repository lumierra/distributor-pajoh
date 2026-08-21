<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Wrench } from '@lucide/vue';
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
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    vehicle: { type: Object, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({ notes: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({ notes: '' });
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    form.post(route('vehicles.maintenance.set', props.vehicle.id), {
        preserveScroll: true,
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
                        <Wrench class="size-5" />
                    </div>
                    <div class="pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">Set Maintenance</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Vehicle akan ter-exclude dari dropdown DO selama maintenance.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-6 pb-2 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Catatan Maintenance</Label>
                    <Textarea v-model="form.notes" rows="3" class="rounded-xl" placeholder="Mis. Ganti oli, tune-up rutin, dst." />
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
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Memproses…' : 'Set Maintenance' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
