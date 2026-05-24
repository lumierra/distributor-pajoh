<script setup>
import { Head, router } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    templates: { type: Object, required: true },
});

const editing = ref({});

function save(id) {
    router.put(route('wa.templates.update', id), editing.value[id], { preserveScroll: true });
}

function startEdit(t) {
    editing.value[t.id] = { body: t.body, is_active: t.is_active, description: t.description };
}
</script>

<template>
    <Head title="WA Templates" />

    <AppLayout>
        <PageHeader title="WhatsApp Templates" description="Edit template per kategori notif." :icon="FileText" />

        <section class="space-y-3">
            <div v-for="t in templates.data" :key="t.id" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">{{ t.category }}</p>
                        <h3 class="text-sm font-semibold">{{ t.name }}</h3>
                    </div>
                    <Button v-if="!editing[t.id]" size="sm" variant="outline" @click="startEdit(t)">Edit</Button>
                </div>

                <div v-if="editing[t.id]" class="space-y-2">
                    <div>
                        <Label class="text-[10px]">Body</Label>
                        <Textarea v-model="editing[t.id].body" rows="5" />
                    </div>
                    <div>
                        <Label class="text-[10px]">Description</Label>
                        <Input v-model="editing[t.id].description" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="editing[t.id].is_active" type="checkbox" :id="`active-${t.id}`" />
                        <Label :for="`active-${t.id}`" class="text-xs">Active</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" @click="save(t.id)">Save</Button>
                        <Button size="sm" variant="ghost" @click="delete editing[t.id]">Cancel</Button>
                    </div>
                </div>
                <pre v-else class="text-sm bg-muted/30 p-3 rounded whitespace-pre-wrap font-sans">{{ t.body }}</pre>
                <p v-if="t.available_placeholders?.length" class="text-[11px] text-muted-foreground mt-2">
                    Placeholders: {{ t.available_placeholders.join(', ') }}
                </p>
            </div>
        </section>
    </AppLayout>
</template>
