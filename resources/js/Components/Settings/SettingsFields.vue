<script setup>
/**
 * Render satu group field setting (schema-driven) ke dalam form macOS.
 * Field dirender sesuai `type`: bool→Switch, int/float→number, options→Select,
 * string panjang→Textarea, string biasa→Input. Menghormati is_readonly.
 *
 * v-model = objek { [key]: value } yang di-mutate langsung.
 */
import { Lock } from '@lucide/vue';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

defineProps({
    fields: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// helper: tebak apakah string sebaiknya textarea (key mengandung text/instruction/note)
function isLongText(field) {
    return /text|instruction|note|address|description|term/i.test(field.key);
}
</script>

<template>
    <div class="divide-y divide-foreground/5">
        <div
            v-for="field in fields"
            :key="field.key"
            class="flex items-start justify-between gap-6 py-3.5 first:pt-0 last:pb-0"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <p class="text-sm font-medium text-foreground">{{ field.label }}</p>
                    <Lock v-if="field.is_readonly" class="size-3 text-muted-foreground" />
                    <span v-if="field.is_sensitive" class="text-[10px] uppercase tracking-wide font-semibold text-amber-600 bg-amber-50 rounded-full px-1.5 py-0.5">sensitif</span>
                </div>
                <p v-if="field.description" class="text-[12px] text-muted-foreground mt-0.5 leading-snug">
                    {{ field.description }}
                </p>
            </div>

            <div class="shrink-0 w-[240px] flex justify-end">
                <!-- Boolean → Switch -->
                <Switch
                    v-if="field.type === 'bool'"
                    :model-value="!!modelValue[field.key]"
                    :disabled="disabled || field.is_readonly"
                    @update:model-value="(v) => emit('update:modelValue', { ...modelValue, [field.key]: v })"
                />

                <!-- Options → Select -->
                <Select
                    v-else-if="field.options && Object.keys(field.options).length"
                    :model-value="String(modelValue[field.key] ?? '')"
                    :disabled="disabled || field.is_readonly"
                    @update:model-value="(v) => emit('update:modelValue', { ...modelValue, [field.key]: v })"
                >
                    <SelectTrigger class="h-9 w-full rounded-xl">
                        <SelectValue placeholder="—" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="(label, val) in field.options" :key="val" :value="String(val)">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Number -->
                <Input
                    v-else-if="field.type === 'int' || field.type === 'float'"
                    :model-value="modelValue[field.key]"
                    type="number"
                    :step="field.type === 'float' ? '0.01' : '1'"
                    :disabled="disabled || field.is_readonly"
                    class="h-9 w-full rounded-xl text-right font-mono"
                    @update:model-value="(v) => emit('update:modelValue', { ...modelValue, [field.key]: v })"
                />

                <!-- Long text → Textarea -->
                <Textarea
                    v-else-if="isLongText(field)"
                    :model-value="modelValue[field.key]"
                    rows="2"
                    :disabled="disabled || field.is_readonly"
                    class="w-full rounded-xl"
                    @update:model-value="(v) => emit('update:modelValue', { ...modelValue, [field.key]: v })"
                />

                <!-- Default string -->
                <Input
                    v-else
                    :model-value="modelValue[field.key]"
                    :disabled="disabled || field.is_readonly"
                    class="h-9 w-full rounded-xl"
                    @update:model-value="(v) => emit('update:modelValue', { ...modelValue, [field.key]: v })"
                />
            </div>
        </div>
    </div>
</template>
