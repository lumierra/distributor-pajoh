<script setup>
/**
 * Wadah pill untuk action buttons di table row.
 *
 *   <ActionGroup>
 *     <ActionButton :icon="Eye"   label="Detail"  tone="brand"   @click="..." />
 *     <ActionButton :icon="Pencil" label="Edit"   tone="blue"    @click="..." />
 *     <ActionButton :icon="Trash2" label="Hapus"  tone="red"     @click="..." />
 *   </ActionGroup>
 */
</script>

<template>
    <div class="inline-flex items-center bg-muted rounded-lg p-1 gap-0.5">
        <slot />
    </div>
</template>
