import { reactive } from 'vue';

/**
 * Global confirm dialog state — satu instance dipakai seluruh app.
 * <ConfirmDialog> di AppLayout.vue merender state ini.
 */
const state = reactive({
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Ya',
    cancelLabel: 'Batal',
    destructive: false,
    resolve: null,
});

/**
 * Pengganti window.confirm() yang non-blocking & bisa dipoles.
 *
 *   if (!(await confirm({ title: 'Hapus supplier PT. Mayora?' }))) return;
 *
 * @param {{ title: string, description?: string, confirmLabel?: string, cancelLabel?: string, destructive?: boolean }} opts
 * @returns {Promise<boolean>}
 */
export function confirm(opts) {
    return new Promise((resolve) => {
        state.title = opts.title;
        state.description = opts.description ?? '';
        state.confirmLabel = opts.confirmLabel ?? (opts.destructive ? 'Hapus' : 'Ya');
        state.cancelLabel = opts.cancelLabel ?? 'Batal';
        state.destructive = opts.destructive ?? false;
        state.resolve = resolve;
        state.open = true;
    });
}

export function useConfirmState() {
    return state;
}
