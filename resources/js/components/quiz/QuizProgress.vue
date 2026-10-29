<script setup lang="ts">
import { computed } from "vue";
import { QUIZ_STRINGS } from "@/lib/quizUtils";

const props = defineProps<{
    current: number;
    total: number;
}>();

const percentage = computed<number>(() => {
    if (props.total <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round((props.current / props.total) * 100)));
});
</script>

<template>
    <div class="space-y-2">
        <div
            class="flex items-center justify-between text-xs font-medium text-neutral-600 dark:text-neutral-400"
        >
            <span>{{ QUIZ_STRINGS.questionProgress(current, total) }}</span>
            <span>{{ percentage }}%</span>
        </div>

        <div
            role="progressbar"
            :aria-label="QUIZ_STRINGS.progressAriaLabel"
            :aria-valuenow="current"
            :aria-valuemin="1"
            :aria-valuemax="Math.max(1, total)"
            class="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800"
        >
            <div
                class="h-full rounded-full bg-neutral-900 transition-all duration-300 dark:bg-neutral-100"
                :style="{ width: `${percentage}%` }"
            />
        </div>
    </div>
</template>
