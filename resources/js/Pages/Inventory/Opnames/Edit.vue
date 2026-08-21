<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Loader2 } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    opname: { type: Object, required: true },
});

const form = useForm({
    opname_date: props.opname.opname_date ? String(props.opname.opname_date).slice(0, 10) : '',
    notes: props.opname.notes ?? '',
    items: (props.opname.items ?? []).map((i) => ({
        id: i.id,
        product_name: i.product_name_snapshot,
        product_sku: i.product_sku_snapshot,
        batch_code: i.batch_code_snapshot,
        system_qty: i.system_qty_snapshot,
        counted_qty: i.counted_qty ?? '',
    })),
});

// Variance live per baris (fisik − sistem saat sesi dibuat; final dihitung ulang saat posting).
function variance(row) {
    if (row.counted_qty === '' || row.counted_qty === null) return null;
    return (Number(row.counted_qty) || 0) - Number(row.system_qty);
}

const countedCount = computed(() => form.items.filter((r) => r.counted_qty !== '' && r.counted_qty !== null).length);

function submit() {
    form
        .transform((data) => ({
            opname_date: data.opname_date,
            notes: data.notes,
            items: data.items.map((r) => ({
                id: r.id,
                counted_qty: r.counted_qty === '' ? null : Number(r.counted_qty),
            })),
        }))
        .put(route('opnames.update', props.opname.id));
}
</script>

<template>
    <Head :title="`Isi Opname ${opname.opname_number}`" />

    <AppLayout>
        <PageHeader
            :title="`Isi Hitung — ${opname.opname_number}`"
            description="Isi jumlah hasil hitung fisik per batch. Baris yang tidak diisi dianggap belum dihitung."
            :icon="ClipboardCheck"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('opnames.show', opname.id)">
                        <ArrowLeft class="size-4" />
                        Kembali ke Detail
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <form @submit.prevent="submit">
            <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm p-5 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Tanggal Opname *</Label>
                        <Input v-model="form.opname_date" type="date" required class="h-10 rounded-xl" />
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <Label class="text-xs font-medium">Catatan</Label>
                        <Textarea v-model="form.notes" rows="1" class="rounded-xl" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden mb-4">
                <header class="px-5 py-3.5 flex items-center justify-between border-b border-foreground/5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Item ({{ form.items.length }})</p>
                    <p class="text-xs text-muted-foreground">Sudah dihitung: <strong class="font-mono text-foreground">{{ countedCount }}</strong> / {{ form.items.length }}</p>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-foreground/5">
                                <th class="text-left py-2.5 px-5 font-semibold">Produk</th>
                                <th class="text-left py-2.5 px-3 font-semibold">Batch</th>
                                <th class="text-right py-2.5 px-3 font-semibold">Stok Sistem</th>
                                <th class="text-right py-2.5 px-3 font-semibold w-[140px]">Hitung Fisik</th>
                                <th class="text-right py-2.5 px-5 font-semibold">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-foreground/5">
                            <tr v-for="row in form.items" :key="row.id" class="hover:bg-foreground/2.5 transition-colors">
                                <td class="py-2.5 px-5">
                                    <p class="font-medium text-xs">{{ row.product_name }}</p>
                                    <p class="text-[11px] text-muted-foreground font-mono">{{ row.product_sku }}</p>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-xs">{{ row.batch_code }}</td>
                                <td class="py-2.5 px-3 text-right font-mono">{{ Number(row.system_qty).toLocaleString('id-ID') }}</td>
                                <td class="py-2 px-3">
                                    <Input
                                        v-model="row.counted_qty"
                                        type="number"
                                        min="0"
                                        placeholder="—"
                                        class="h-9 rounded-lg text-right font-mono"
                                    />
                                </td>
                                <td class="py-2.5 px-5 text-right font-mono">
                                    <span v-if="variance(row) === null" class="text-muted-foreground">—</span>
                                    <span v-else-if="variance(row) === 0" class="text-muted-foreground">0</span>
                                    <span v-else :class="variance(row) > 0 ? 'text-emerald-700' : 'text-red-600'">
                                        {{ variance(row) > 0 ? '+' : '' }}{{ variance(row) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-muted-foreground px-5 py-3">
                    Selisih = hitung fisik − stok sistem. <span class="text-emerald-700">Hijau</span> = fisik lebih banyak (stok ditambah),
                    <span class="text-red-600">merah</span> = fisik lebih sedikit (stok dikurangi). Angka final dihitung ulang saat posting.
                </p>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline" size="default" class="rounded-full">
                    <Link :href="route('opnames.show', opname.id)">Batal</Link>
                </Button>
                <Button
                    type="submit"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Hitung' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
