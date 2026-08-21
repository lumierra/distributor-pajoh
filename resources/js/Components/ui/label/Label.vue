<script setup lang="ts">
import type { LabelProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { Label } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<LabelProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')
</script>

<template>
  <Label
    data-slot="label"
    v-bind="delegatedProps"
    :class="
      cn(
        // `mb-1.5` = jarak default label → input untuk semua form bertumpuk,
        // supaya tidak perlu lagi mengandalkan wrapper `space-y-*` di tiap halaman.
        // Label yang sejajar Switch/Checkbox menetralkannya dengan `mb-0`
        // lewat prop `class` agar perataan vertikalnya tidak bergeser.
        'gap-2 text-xs/relaxed leading-none font-medium group-data-[disabled=true]:opacity-50 peer-disabled:opacity-50 flex items-center select-none group-data-[disabled=true]:pointer-events-none peer-disabled:cursor-not-allowed mb-1.5',
        props.class,
      )
    "
  >
    <slot />
  </Label>
</template>
