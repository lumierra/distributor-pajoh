<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, UserCog } from '@lucide/vue';
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
    customer: { type: Object, required: true },
    salesUsers: { type: Array, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({ assigned_sales_id: null, reason: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({
            assigned_sales_id: props.customer.assigned_sales_id ?? null,
            reason: '',
        });
        form.reset();
        form.clearErrors();
    },
);

function submit() {
    form.post(route('customers.reassign-sales', props.customer.id), {
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
                        <UserCog class="size-5" />
                    </div>
                    <div class="pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            Reassign Sales
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Sales aktual tetap dicatat di SO. Ini hanya default kunjungan.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-6 pb-2 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Sales Baru *</Label>
                    <Select
                        :model-value="form.assigned_sales_id ? String(form.assigned_sales_id) : ''"
                        @update:model-value="(v) => (form.assigned_sales_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger class="h-10 w-full rounded-xl">
                            <SelectValue placeholder="Pilih sales" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">
                                {{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.assigned_sales_id" class="text-xs text-destructive">
                        {{ form.errors.assigned_sales_id }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Alasan (opsional)</Label>
                    <Textarea v-model="form.reason" rows="2" class="rounded-xl" placeholder="Mis. Sales lama resign." />
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
                    :disabled="form.processing || !form.assigned_sales_id"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Reassign' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
