<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, MapPin } from '@lucide/vue';
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

const props = defineProps({
    open: { type: Boolean, default: false },
    customer: { type: Object, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({ latitude: '', longitude: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({
            latitude: props.customer.latitude ?? '',
            longitude: props.customer.longitude ?? '',
        });
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    form.put(route('customers.geo.update', props.customer.id), {
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
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <MapPin class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Set Koordinat Outlet
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Map picker Leaflet menyusul. MVP: input manual lat/lng.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Latitude *</Label>
                        <Input
                            v-model="form.latitude"
                            type="number"
                            step="0.0000001"
                            required
                            class="h-9 font-mono"
                            placeholder="4.4683"
                        />
                        <p v-if="form.errors.latitude" class="text-xs text-destructive">{{ form.errors.latitude }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Longitude *</Label>
                        <Input
                            v-model="form.longitude"
                            type="number"
                            step="0.0000001"
                            required
                            class="h-9 font-mono"
                            placeholder="97.9740"
                        />
                        <p v-if="form.errors.longitude" class="text-xs text-destructive">{{ form.errors.longitude }}</p>
                    </div>
                </div>
                <div class="rounded-md bg-warning-soft ring-1 ring-warning/20 px-3 py-2 text-xs">
                    💡 Tip: ambil koordinat dari Google Maps (klik kanan → "Salin koordinat").
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
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Koordinat' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
