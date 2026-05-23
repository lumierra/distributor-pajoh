<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: String,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen flex items-center justify-center bg-body p-6">
        <div class="max-w-2xl w-full bg-surface rounded-2xl shadow-lg p-12 text-center">
            <div class="mb-6">
                <h1 class="text-4xl font-bold text-brand">
                    {{ appName ?? 'Pajoh Distributor' }}
                </h1>
                <p class="mt-2 text-muted-foreground">
                    Sistem Pergudangan & Distribusi Pajoh
                </p>
            </div>

            <div class="space-y-2 text-sm text-muted-foreground border-t border-border pt-6">
                <div class="flex justify-center gap-6">
                    <span>Laravel {{ laravelVersion }}</span>
                    <span>PHP {{ phpVersion }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
