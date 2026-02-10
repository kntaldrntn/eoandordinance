<script setup>
import { Clock, Download, FileText } from 'lucide-vue-next';

defineProps({
    timeline: Array
});
</script>

<template>
    <div class="mt-6 pt-6 border-t border-gray-100">
        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
            <Clock class="w-4 h-4" /> Record History
        </h4>

        <div v-if="timeline && timeline.length > 0" class="relative border-l-2 border-gray-100 ml-2 space-y-6">
            <div v-for="(log, index) in timeline" :key="index" class="relative pl-6">
                
                <div class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full border-2 border-white" 
                    :class="{
                        'bg-green-500': log.action === 'Record Published',
                        'bg-amber-500': log.action.includes('Amended'),
                        'bg-indigo-500': !['Record Published', 'Amended'].includes(log.action)
                    }"
                ></div>

                <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            {{ log.action }}
                        </p>

                        <ul class="mt-1 space-y-1">
                            <li v-for="(detail, i) in log.details" :key="i" class="text-xs text-gray-500">
                                <span v-if="detail.is_bold" class="font-semibold text-gray-700 block mb-1">
                                    {{ detail.text }}
                                </span>
                                <span v-else>{{ detail.text }}</span>
                            </li>
                        </ul>

                        <div v-if="log.file_url" class="mt-2">
                            <a :href="log.file_url" target="_blank" 
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-indigo-50 text-indigo-700 text-xs font-medium hover:bg-indigo-100 transition-colors border border-indigo-100"
                            >
                                <Download class="w-3 h-3" />
                                {{ log.file_name || 'Download PDF' }}
                            </a>
                        </div>
                    </div>

                    <span class="text-[10px] font-mono text-gray-400 bg-gray-50 px-2 py-1 rounded w-fit h-fit shrink-0">
                        {{ log.date_display }}
                    </span>
                </div>
            </div>
        </div>
        
        <div v-else class="text-xs text-gray-400 italic pl-2">
            No updates recorded since publication.
        </div>
    </div>
</template>