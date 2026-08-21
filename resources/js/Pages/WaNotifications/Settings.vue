<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MessageSquare, Settings, TestTube, XCircle } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

const form = useForm({
    gateway_url: props.settings.gateway_url ?? '',
    gateway_token: props.settings.gateway_token ?? '',
    sender_id: props.settings.sender_id ?? '',
    rate_limit_per_day: props.settings.rate_limit_per_day ?? 5,
});

const testResult = ref(null);
const testing = ref(false);

function save() {
    form.post(route('wa.settings.update'), { preserveScroll: true });
}

async function testConnection() {
    testing.value = true;
    testResult.value = null;
    try {
        const res = await fetch(route('wa.test-connection'), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        testResult.value = await res.json();
    } catch (e) {
        testResult.value = { success: false, message: e.message };
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <Head title="WA Settings" />

    <AppLayout>
        <PageHeader title="WhatsApp Settings" description="Gateway connection + rate limit." :icon="Settings" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <form class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3" @submit.prevent="save">
                <h3 class="text-sm font-semibold flex items-center gap-2">
                    <MessageSquare class="size-4" />
                    Gateway
                </h3>
                <div>
                    <Label>Gateway URL</Label>
                    <Input v-model="form.gateway_url" placeholder="https://api.wa-gateway.example.com" />
                    <p class="text-[12px] text-muted-foreground mt-1">Endpoint dasar gateway. POST ke <code>/send</code>.</p>
                </div>
                <div>
                    <Label>Gateway Token</Label>
                    <Input v-model="form.gateway_token" type="password" />
                </div>
                <div>
                    <Label>Sender ID</Label>
                    <Input v-model="form.sender_id" placeholder="6281234567890" />
                </div>
                <div>
                    <Label>Rate Limit per Customer per Hari</Label>
                    <Input v-model.number="form.rate_limit_per_day" type="number" min="0" max="100" />
                </div>
                <div class="pt-2">
                    <Button type="submit" :disabled="form.processing">Simpan Setting</Button>
                </div>
            </form>

            <aside class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold flex items-center gap-2">
                    <TestTube class="size-4" />
                    Test Connection
                </h3>
                <p class="text-xs text-muted-foreground">Cek apakah gateway URL + token bekerja (panggil <code>/health</code>).</p>
                <Button class="w-full" :disabled="testing" @click="testConnection">
                    {{ testing ? 'Testing…' : 'Run Test' }}
                </Button>
                <div v-if="testResult" :class="['mt-2 p-2 rounded-md text-xs', testResult.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800']">
                    <div class="flex items-center gap-1.5">
                        <CheckCircle2 v-if="testResult.success" class="size-3.5" />
                        <XCircle v-else class="size-3.5" />
                        <span class="font-semibold">{{ testResult.success ? 'OK' : 'FAILED' }}</span>
                    </div>
                    <p class="mt-1">{{ testResult.message }}</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
