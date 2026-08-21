<script setup>
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Combobox/autocomplete berbasis primitif `reka-ui` (dipakai juga oleh
 * `Select` di seluruh app ini) — bukan reimplementasi posisi manual, supaya
 * positioning & anti-clipping (portal ke body + floating-ui internal)
 * otomatis benar termasuk di dalam container ber-overflow (mis. tabel item
 * PO yang overflow-x-auto), tanpa perlu hitung rect sendiri.
 *
 * `options`: array bebas; filter pencarian bawaan reka-ui mencocokkan
 * textContent tiap `ComboboxItem` yang di-render (nama + SKU, dsb — apa pun
 * yang dirender di slot #option), jadi tidak perlu filter manual.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    optionValue: { type: Function, default: (o) => o.id },
    optionLabel: { type: Function, default: (o) => String(o.name ?? '') },
    placeholder: { type: String, default: 'Ketik untuk cari…' },
    emptyText: { type: String, default: 'Tidak ada opsi cocok.' },
    disabled: { type: Boolean, default: false },
    triggerClass: { type: String, default: 'h-10 rounded-xl' },
    // Label cadangan saat modelValue sudah terisi tapi opsinya belum ada di
    // `options` (mis. katalog masih dimuat via AJAX di halaman edit).
    fallbackLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.options.find((o) => props.optionValue(o) === props.modelValue) ?? null);

// Teks yang tampil di input: label opsi terpilih; kalau opsi belum ketemu tapi
// modelValue ada, pakai fallbackLabel supaya tidak tampil kosong.
const displayText = computed(() => {
    if (selected.value) return props.optionLabel(selected.value);
    if (props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '') {
        return props.fallbackLabel;
    }
    return '';
});

function onModelUpdate(value) {
    emit('update:modelValue', value ?? null);
}
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue"
        :disabled="disabled"
        reset-search-term-on-blur
        open-on-focus
        open-on-click
        @update:model-value="onModelUpdate"
    >
        <ComboboxAnchor class="relative w-full">
            <ComboboxInput
                :key="displayText"
                :display-value="() => displayText"
                :placeholder="placeholder"
                :class="[
                    'w-full rounded-xl bg-input/20 border border-input px-3 pr-8 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring/30 disabled:opacity-50 disabled:cursor-not-allowed placeholder:text-muted-foreground',
                    triggerClass,
                ]"
            />
            <ComboboxTrigger class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground">
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                side="bottom"
                align="start"
                :side-offset="6"
                :avoid-collisions="true"
                class="z-50 w-(--reka-combobox-trigger-width) rounded-2xl bg-popover ring-1 ring-foreground/10 shadow-lg overflow-hidden"
            >
                <ComboboxViewport class="max-h-56 overflow-y-auto py-1">
                    <ComboboxEmpty class="px-3 py-3 text-xs text-muted-foreground text-center">
                        {{ emptyText }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in options"
                        :key="optionValue(option)"
                        :value="optionValue(option)"
                        class="px-3 py-2 flex items-center gap-2.5 hover:bg-foreground/5 data-highlighted:bg-foreground/5 cursor-pointer transition-colors outline-none"
                    >
                        <div
                            :class="[
                                'size-4 rounded-full flex items-center justify-center shrink-0',
                                optionValue(option) === modelValue ? 'bg-brand text-white' : 'ring-1 ring-muted-foreground/30',
                            ]"
                        >
                            <ComboboxItemIndicator>
                                <Check class="size-3" />
                            </ComboboxItemIndicator>
                        </div>
                        <div class="min-w-0 flex-1">
                            <slot name="option" :option="option">
                                <p class="text-sm font-medium truncate">{{ optionLabel(option) }}</p>
                            </slot>
                        </div>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
