<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Gavel } from 'lucide-vue-next';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <div
        class="bg-blue-600 rounded-full shadow-sm flex items-center justify-center aspect-square"
        :class="className"
        v-bind="$attrs"
    >
        <Gavel class="w-[60%] h-[60%] text-white" stroke-width="2.5" />
    </div>
</template>