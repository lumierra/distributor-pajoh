<script setup>
import { useForm } from '@inertiajs/vue3';
import { Loader2, Plus, Save, Search, Tags, Trash2 } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    open: { type: Boolean, default: false },
    customer: { type: Object, required: true },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'saved']);

const loading = ref(false);
// Produk yang sedang di-set (assigned). [{ product_id, product_sku, product_name, packages:[{id,name}], price_package_id }]
const rows = ref([]);

// ── Pencarian produk untuk ditambahkan ───────────────────────────────
const search = ref('');
const searchResults = ref([]); // hasil server, sudah difilter yg belum di-rows
const searching = ref(false);
const showResults = ref(false);
const searchBox = ref(null);
let searchTimer = null;

// Tutup dropdown hasil kalau klik di luar area search.
function onDocClick(e) {
    if (searchBox.value && !searchBox.value.contains(e.target)) {
        showResults.value = false;
    }
}
document.addEventListener('click', onDocClick);
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

async function load() {
    loading.value = true;
    try {
        const res = await fetch(
            route('customers.price-packages.index', props.customer.id),
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        if (!res.ok) throw new Error('Failed to load');
        const data = await res.json();
        rows.value = (data.rows ?? []).map((r) => ({
            ...r,
            // Default ke paket pertama produk kalau belum ada pilihan tersimpan.
            price_package_id: r.price_package_id ?? r.packages?.[0]?.id ?? null,
        }));
    } finally {
        loading.value = false;
    }
}

async function runSearch() {
    const q = search.value.trim();
    searching.value = true;
    showResults.value = true;
    try {
        const res = await fetch(
            route('customers.price-packages.search', props.customer.id) + `?q=${encodeURIComponent(q)}`,
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        );
        if (!res.ok) throw new Error('search failed');
        const data = await res.json();
        const assignedIds = new Set(rows.value.map((r) => r.product_id));
        searchResults.value = (data.rows ?? []).filter((r) => !assignedIds.has(r.product_id));
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 250);
});

function addProduct(p) {
    if (rows.value.some((r) => r.product_id === p.product_id)) return;
    rows.value.unshift({
        ...p,
        price_package_id: p.packages?.[0]?.id ?? null, // default paket pertama
    });
    // Buang dari hasil pencarian & tutup dropdown, reset query.
    searchResults.value = searchResults.value.filter((r) => r.product_id !== p.product_id);
    search.value = '';
    showResults.value = false;
}

function removeRow(productId) {
    rows.value = rows.value.filter((r) => r.product_id !== productId);
}

watch(
    () => props.open,
    (v) => {
        if (v) {
            search.value = '';
            searchResults.value = [];
            showResults.value = false;
            load();
        }
    },
);

const form = useForm({ rows: [] });

function save() {
    // Kirim rows saat ini. Produk yg dihapus dari daftar TIDAK dikirim, jadi
    // assignment lama-nya perlu ikut dihapus → kirim juga sebagai null.
    const current = rows.value.map((r) => ({
        product_id: r.product_id,
        price_package_id: r.price_package_id ?? null,
    }));

    form.rows = current;
    form.put(route('customers.price-packages.sync', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('update:open', false);
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[680px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <Tags class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            Paket Harga per Produk — {{ customer.name }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Cari produk yang ingin dibedakan harganya, lalu pilih paketnya. Hanya produk
                            yang di-set khusus ditampilkan di bawah. Produk lain otomatis pakai harga standar.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Search-to-add -->
            <div v-if="canUpdate" class="px-6 pb-3">
                <div ref="searchBox" class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground z-10" />
                    <Input
                        v-model="search"
                        placeholder="Cari produk untuk ditambah (nama / SKU)…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                        @focus="runSearch"
                    />

                    <!-- Dropdown hasil pencarian -->
                    <div
                        v-if="showResults"
                        class="absolute z-20 mt-1.5 w-full rounded-2xl border border-foreground/8 bg-card shadow-lg overflow-hidden max-h-64 overflow-y-auto"
                    >
                        <div v-if="searching" class="px-4 py-3 text-xs text-muted-foreground flex items-center gap-2">
                            <Loader2 class="size-3.5 animate-spin" /> Mencari…
                        </div>
                        <div v-else-if="searchResults.length === 0" class="px-4 py-3 text-xs text-muted-foreground">
                            Tidak ada produk cocok (atau semua sudah di-set).
                        </div>
                        <button
                            v-for="p in searchResults"
                            :key="p.product_id"
                            type="button"
                            class="w-full text-left px-4 py-2.5 hover:bg-foreground/4 transition-colors flex items-center gap-2 border-b border-foreground/4 last:border-0"
                            @click="addProduct(p)"
                        >
                            <Plus class="size-3.5 text-brand shrink-0" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium truncate">{{ p.product_name }}</span>
                                <span class="block text-[11px] text-muted-foreground font-mono">{{ p.product_sku }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Daftar produk yang sudah di-set -->
            <div class="max-h-[50vh] overflow-y-auto border-t border-foreground/5">
                <Table>
                    <TableHeader>
                        <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                            <TableHead class="pl-4">Produk</TableHead>
                            <TableHead class="text-right">Paket Harga</TableHead>
                            <TableHead v-if="canUpdate" class="w-10 pr-4" />
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="loading">
                            <TableCell :colspan="canUpdate ? 3 : 2" class="text-center py-8 text-muted-foreground">
                                <Loader2 class="size-4 animate-spin inline-block mr-2" />
                                Memuat…
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="rows.length === 0">
                            <TableCell :colspan="canUpdate ? 3 : 2" class="text-center py-10 text-muted-foreground">
                                <Tags class="size-6 opacity-40 mx-auto mb-2" />
                                <p class="text-sm">Belum ada produk di-set khusus.</p>
                                <p v-if="canUpdate" class="text-xs mt-0.5">Cari produk di atas untuk menambah.</p>
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="r in rows"
                            :key="r.product_id"
                            class="hover:bg-foreground/2.5 transition-colors border-foreground/5 bg-emerald-50/30"
                        >
                            <TableCell class="pl-4 py-2.5">
                                <p class="font-medium">{{ r.product_name }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ r.product_sku }}</p>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end">
                                    <Select
                                        v-if="canUpdate"
                                        :model-value="r.price_package_id != null ? String(r.price_package_id) : ''"
                                        @update:model-value="(v) => (r.price_package_id = v ? Number(v) : null)"
                                    >
                                        <SelectTrigger class="h-8 w-48 rounded-lg text-xs">
                                            <SelectValue placeholder="Pilih paket" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="pkg in r.packages"
                                                :key="pkg.id"
                                                :value="String(pkg.id)"
                                            >
                                                {{ pkg.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <span v-else class="text-xs">
                                        {{ (r.packages.find((p) => p.id === r.price_package_id) || {}).name || '—' }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell v-if="canUpdate" class="pr-4 text-right">
                                <button
                                    type="button"
                                    class="size-7 rounded-full inline-flex items-center justify-center text-muted-foreground hover:text-red-600 hover:bg-red-50 transition-colors"
                                    title="Hapus (kembali ke harga standar)"
                                    @click="removeRow(r.product_id)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="$emit('update:open', false)">
                    Tutup
                </Button>
                <Button
                    v-if="canUpdate"
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing || loading"
                    @click="save"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
