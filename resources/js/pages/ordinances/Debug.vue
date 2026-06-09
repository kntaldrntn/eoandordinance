<template>
  <div class="p-6">
    <h2 class="text-lg font-bold mb-4">Ordinance Debug</h2>
    <pre class="whitespace-pre-wrap bg-gray-100 p-4 rounded">{{ pretty }}</pre>
    <div class="mt-4">
      <inertia-link :href="route('ordinances.index')" class="text-blue-600">Back to Ordinances</inertia-link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
const page = usePage();
const debug = page.props.value.debug || {};
const pretty = computed(() => JSON.stringify(debug, null, 2));
</script>
