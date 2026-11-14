<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import { index as reportsIndex } from '@/routes/reports';

type Report = {
    key: string;
    title: string;
    description: string;
    group: string;
};
const props = defineProps<{ reports: Report[] }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: reportsIndex() }] },
});

const groups = computed(() => {
    const g: Record<string, Report[]> = {};
    props.reports.forEach((r) => (g[r.group] ??= []).push(r));
    return g;
});
</script>

<template>
    <Head title="Reports" />
    <div class="flex flex-col gap-5 p-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Reports</h1>
            <p class="text-sm text-muted-foreground">
                Every report filters by date and exports to Excel and PDF.
            </p>
        </div>
        <section
            v-for="(list, group) in groups"
            :key="group"
            class="flex flex-col gap-2"
        >
            <h2
                class="text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                {{ group }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="r in list"
                    :key="r.key"
                    :href="ReportController.show(r.key).url"
                    class="flex flex-col gap-1 rounded-xl border p-4 transition hover:border-primary hover:bg-primary/5"
                >
                    <span class="font-semibold">{{ r.title }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        r.description
                    }}</span>
                </Link>
            </div>
        </section>
    </div>
</template>
