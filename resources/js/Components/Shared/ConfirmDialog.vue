<script setup>
import { useConfirmState } from '@/Composables/useConfirm';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

const state = useConfirmState();

function settle(result) {
    if (!state.resolve) return;
    state.resolve(result);
    state.resolve = null;
}

function onOpenChange(open) {
    state.open = open;
    // Dialog ditutup TANPA lewat tombol konfirmasi (Cancel, Escape, klik di
    // luar) → dibatalkan. Tombol konfirmasi memakai @click.capture di
    // handleConfirm yang sudah menyetel state.resolve = null lebih dulu,
    // jadi settle(false) di sini otomatis no-op untuk kasus itu.
    if (!open) {
        settle(false);
    }
}

function handleConfirm() {
    // Jalan di fase CAPTURE (lihat @click.capture di template) sehingga
    // resolve(true) terjadi SEBELUM DialogClose bawaan reka-ui memicu
    // onOpenChange(false) di fase bubble. Ini memutus race condition.
    settle(true);
    state.open = false;
}
</script>

<template>
    <AlertDialog :open="state.open" @update:open="onOpenChange">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ state.title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="state.description">
                    {{ state.description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ state.cancelLabel }}</AlertDialogCancel>
                <AlertDialogAction
                    :class="state.destructive ? 'bg-destructive/15 text-destructive hover:bg-destructive/25' : ''"
                    @click.capture="handleConfirm"
                >
                    {{ state.confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
