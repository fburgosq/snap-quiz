<script setup lang="ts">
import { QUIZ_STRINGS } from "@/lib/quizUtils";

defineProps<{
    visible: boolean;
    isCorrect: boolean;
    reason: string;
}>();
</script>

<template>
    <div aria-live="polite" class="min-h-4">
        <div
            v-if="visible"
            class="rounded-xl border p-4"
            :class="
                isCorrect
                    ? 'border-emerald-300 bg-emerald-50/80 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                    : 'border-red-300 bg-red-50/80 text-red-950 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200'
            "
        >
            <div class="flex items-center gap-2 text-sm font-semibold">
                <span aria-hidden="true">{{ isCorrect ? "✓" : "✗" }}</span>
                <span>
                    {{ isCorrect ? QUIZ_STRINGS.statusCorrect : QUIZ_STRINGS.statusIncorrect }}
                </span>
            </div>

            <p
                v-if="reason"
                class="mt-1.5 text-sm leading-relaxed text-neutral-800 dark:text-neutral-200"
            >
                {{ reason }}
            </p>
        </div>
    </div>
</template>
