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
        <DialogContent class="sm:max-w-[480px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <UserCog class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold tracking-tight">
                            Reassign Sales
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Sales aktual tetap dicatat di SO. Ini hanya default kunjungan.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form class="px-5 py-4 space-y-3.5" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Sales Baru *</Label>
                    <Select
                        :model-value="form.assigned_sales_id ? String(form.assigned_sales_id) : ''"
                        @update:model-value="(v) => (form.assigned_sales_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger class="h-9">
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
                    <Textarea v-model="form.reason" rows="2" placeholder="Mis. Sales lama resign." />
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
