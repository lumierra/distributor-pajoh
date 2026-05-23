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
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 ring-1 ring-amber-200">
                        <Wrench class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">Set Maintenance</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Vehicle akan ter-exclude dari dropdown DO selama maintenance.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Catatan Maintenance</Label>
                    <Textarea v-model="form.notes" rows="3" placeholder="Mis. Ganti oli, tune-up rutin, dst." />
                </div>
            </form>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="$emit('update:open', false)">Batal</Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="default"
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
