<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Loader2, LogIn } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({
    companyName: { type: String, default: 'Pajoh Distributor' },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <main
        class="min-h-screen flex items-center justify-center px-4 py-10 relative overflow-hidden"
    >
        <!-- Background flair -->
        <div
            class="absolute inset-0 -z-10"
            style="
                background:
                    radial-gradient(60% 50% at 50% 0%, rgba(160, 50, 50, 0.07) 0%, transparent 70%),
                    radial-gradient(50% 40% at 50% 100%, rgba(16, 72, 55, 0.05) 0%, transparent 70%),
                    var(--background);
            "
        ></div>
        <div
            class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-30 -z-10"
            style="background: radial-gradient(circle, var(--primary) 0%, transparent 70%);"
        ></div>

        <div class="w-full max-w-md space-y-7 animate-fade-in-up">
            <div class="text-center space-y-2.5">
                <div
                    class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand text-white font-bold text-xl shadow-md"
                >
                    P
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    {{ companyName }}
                </h1>
                <p class="text-sm text-muted-foreground">Sistem Distribusi &amp; Pergudangan</p>
            </div>

            <section class="rounded-3xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-md overflow-hidden">
                <header class="px-6 pt-6 pb-2">
                    <h2 class="text-base font-semibold">Masuk ke akun Anda</h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        Gunakan username & password yang diberikan administrator.
                    </p>
                </header>

                <form class="space-y-4 p-6" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label for="username">Username</Label>
                        <Input
                            id="username"
                            v-model="form.username"
                            type="text"
                            autocomplete="username"
                            placeholder="username"
                            autofocus
                            required
                            class="h-10 rounded-xl"
                            :aria-invalid="!!form.errors.username"
                            :disabled="form.processing"
                        />
                        <p v-if="form.errors.username" class="text-xs text-destructive">
                            {{ form.errors.username }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="password">Password</Label>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                required
                                class="pr-10 h-10 rounded-xl"
                                :aria-invalid="!!form.errors.password"
                                :disabled="form.processing"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center justify-center w-10 text-muted-foreground hover:text-foreground transition-colors"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Lihat password'"
                                tabindex="-1"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-destructive">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <Checkbox id="remember" v-model="form.remember" />
                        <Label for="remember" class="text-xs font-normal cursor-pointer mb-0">
                            Ingat saya selama 30 hari
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full rounded-full bg-brand text-white hover:bg-brand-dark"
                        :disabled="form.processing || !form.username || !form.password"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        <LogIn v-else class="size-4" />
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </Button>
                </form>
            </section>

            <p class="text-center text-xs text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ companyName }}
            </p>
        </div>
    </main>
</template>
