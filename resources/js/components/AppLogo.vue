<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = computed(() => page.props.name);
const logo = computed(() => page.props.branding?.logo ?? null);
</script>

<template>
    <div
        v-if="logo"
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-white"
    >
        <img :src="logo" alt="" class="size-8 object-contain" />
    </div>
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
