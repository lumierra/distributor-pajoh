<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackageOpen } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
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
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplierReturn: { type: Object, required: true },
});

const form = useForm({
    return_date: props.supplierReturn.return_date,
    reason_code: props.supplierReturn.reason_code,
    reason_notes: props.supplierReturn.reason_notes ?? '',
    notes: props.supplierReturn.notes ?? '',
});

function submit() {
    form.put(route('supplier-returns.update', props.supplierReturn.id));
}
</script>

<template>
    <Head title="Edit Retur Supplier" />

    <AppLayout>
        <PageHeader :title="`Edit ${supplierReturn.return_number}`" :icon="PackageOpen">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('supplier-returns.show', supplierReturn.id)">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form class="grid grid-cols-1 lg:grid-cols-3 gap-4" @submit.prevent="submit">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Tgl Retur</Label>
                        <Input v-model="form.return_date" type="date" />
                    </div>
                    <div>
                        <Label>Reason</Label>
                        <Select v-model="form.reason_code">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="damaged">Damaged</SelectItem>
                                <SelectItem value="expired">Expired</SelectItem>
                                <SelectItem value="quality">Quality</SelectItem>
                                <SelectItem value="wrong_item">Wrong Item</SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div>
                    <Label>Reason Notes</Label>
                    <Textarea v-model="form.reason_notes" rows="2" />
                </div>
                <div>
                    <Label>Notes</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
            </section>
            <aside class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 h-fit">
                <Button type="submit" :disabled="form.processing" class="w-full">Simpan Perubahan</Button>
            </aside>
        </form>
    </AppLayout>
</template>
