<script setup>
import { computed, ref, watch } from 'vue';
import { Input } from '@/Components/ui/input';

/**
 * Input angka dgn live-format ribuan ala Rupiah ("50000" -> "50.000") saat
 * diketik. v-model tetap number murni (bukan string berformat) supaya form
 * payload tidak perlu di-parse ulang sebelum submit.
 */
const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    class: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

function toDigits(v) {
    return String(v ?? '').replace(/\D/g, '');
}
function formatDigits(digits) {
    if (!digits) return '';
    return new Intl.NumberFormat('id-ID').format(Number(digits));
}

const display = ref(formatDigits(toDigits(props.modelValue)));

watch(
    () => props.modelValue,
    (v) => {
        const digits = toDigits(v);
        if (digits !== toDigits(display.value)) {
            display.value = formatDigits(digits);
        }
    },
);

function onInput(e) {
    const digits = toDigits(e.target.value);
    display.value = formatDigits(digits);
    emit('update:modelValue', digits ? Number(digits) : 0);
}

const prefixedClass = computed(() => props.class);
</script>

<template>
    <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm pointer-events-none">
            Rp
        </span>
        <Input
            :model-value="display"
            inputmode="numeric"
            autocomplete="off"
            :class="['pl-8 text-left', prefixedClass]"
            @input="onInput"
        />
    </div>
</template>
