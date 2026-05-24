<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PackagePlus } from '@lucide/vue';
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
    customerReturn: { type: Object, required: true },
    products: { type: Array, default: () => [] },
});

const form = useForm({
    return_date: props.customerReturn.return_date,
    invoice_id: props.customerReturn.invoice_id ?? '',
    brand_tag: props.customerReturn.brand_tag ?? '',
    reason_code: props.customerReturn.reason_code ?? '',
    reason_notes: props.customerReturn.reason_notes ?? '',
    notes: props.customerReturn.notes ?? '',
});

function submit() {
    form.put(route('customer-returns.update', props.customerReturn.id));
}
</script>

<template>
    <Head title="Edit Retur" />

    <AppLayout>
        <PageHeader :title="`Edit ${customerReturn.return_number}`" :icon="PackagePlus">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('customer-returns.show', customerReturn.id)">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form class="grid grid-cols-1 lg:grid-cols-3 gap-4" @submit.prevent="submit">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Detail Header</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Tgl Retur</Label>
                        <Input v-model="form.return_date" type="date" />
                    </div>
                    <div>
                        <Label>Brand Tag</Label>
                        <Input v-model="form.brand_tag" />
                    </div>
                    <div>
                        <Label>Reason</Label>
                        <Select v-model="form.reason_code">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="expired">Expired</SelectItem>
                                <SelectItem value="damaged">Damaged</SelectItem>
                                <SelectItem value="wrong_item">Wrong Item</SelectItem>
                                <SelectItem value="customer_request">Customer Request</SelectItem>
                                <SelectItem value="quality_issue">Quality Issue</SelectItem>
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
                    <Label>Catatan</Label>
                    <Textarea v-model="form.notes" rows="2" />
                </div>
            </section>

            <aside class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 h-fit">
                <Button type="submit" :disabled="form.processing" class="w-full">Simpan Perubahan</Button>
            </aside>
        </form>
    </AppLayout>
</template>
