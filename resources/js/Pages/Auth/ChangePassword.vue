<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Loader2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({
    forced: { type: Boolean, default: false },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name || '');

const form = useForm({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

function submit() {
    form.post(route('password.change.store'), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Ganti Password" />

    <main class="min-h-screen flex items-center justify-center px-4 py-10 bg-background">
        <div class="w-full max-w-md space-y-6 animate-fade-in-up">
            <section class="rounded-3xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-md overflow-hidden">
                <header class="px-6 pt-6 pb-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0"
                        >
                            <KeyRound class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold">Ganti Password</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ userName }}
                                <span v-if="forced" class="text-destructive font-medium">
                                    · Wajib ganti sebelum melanjutkan
                                </span>
                            </p>
                        </div>
                    </div>
                </header>

                <form class="space-y-4 p-6" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Password saat ini</Label>
                        <Input
                            v-model="form.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="h-10 rounded-xl"
                            :aria-invalid="!!form.errors.current_password"
                            :disabled="form.processing"
                        />
                        <p v-if="form.errors.current_password" class="text-xs text-destructive">
                            {{ form.errors.current_password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Password baru</Label>
                        <Input
                            v-model="form.new_password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="h-10 rounded-xl"
                            :aria-invalid="!!form.errors.new_password"
                            :disabled="form.processing"
                        />
                        <ul
                            v-if="Array.isArray(form.errors.new_password)"
                            class="text-xs text-destructive list-disc pl-5 space-y-0.5"
                        >
                            <li v-for="msg in form.errors.new_password" :key="msg">{{ msg }}</li>
                        </ul>
                        <p v-else-if="form.errors.new_password" class="text-xs text-destructive">
                            {{ form.errors.new_password }}
                        </p>
                        <p class="text-[12px] text-muted-foreground">
                            Minimal 8 karakter, harus mengandung huruf dan angka. Tidak boleh sama
                            dengan 3 password terakhir.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Konfirmasi password baru</Label>
                        <Input
                            v-model="form.new_password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="h-10 rounded-xl"
                            :disabled="form.processing"
                        />
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full rounded-full bg-brand text-white hover:bg-brand-dark"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{ form.processing ? 'Memproses…' : 'Simpan password baru' }}
                    </Button>
                </form>
            </section>
        </div>
    </main>
</template>
