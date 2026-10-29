<script setup lang="ts">
import { QUIZ_STRINGS, type QuizMode } from "@/lib/quizUtils";

const emit = defineEmits<{
    select: [mode: QuizMode];
}>();

const modes: { id: QuizMode; title: string; description: string }[] = [
    {
        id: "multiple_choice",
        title: QUIZ_STRINGS.modes.multiple_choice.title,
        description: QUIZ_STRINGS.modes.multiple_choice.description,
    },
    {
        id: "open_answer",
        title: QUIZ_STRINGS.modes.open_answer.title,
        description: QUIZ_STRINGS.modes.open_answer.description,
    },
    {
        id: "yes_no",
        title: QUIZ_STRINGS.modes.yes_no.title,
        description: QUIZ_STRINGS.modes.yes_no.description,
    },
];
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ QUIZ_STRINGS.chooseModeHeading }}
            </h2>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ QUIZ_STRINGS.chooseModeSubheading }}
            </p>
        </div>

        <div class="grid gap-3">
            <button
                v-for="item in modes"
                :key="item.id"
                type="button"
                :data-test="`mode-${item.id}`"
                class="flex min-h-14 w-full flex-col items-start justify-center rounded-xl border border-neutral-200 bg-white p-4 text-left transition hover:border-neutral-400 hover:bg-neutral-50 focus:ring-2 focus:ring-neutral-900 focus:outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-600 dark:hover:bg-neutral-800/70 dark:focus:ring-neutral-100"
                @click="emit('select', item.id)"
            >
                <span class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ item.title }}
                </span>
                <span class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ item.description }}
                </span>
            </button>
        </div>
    </div>
</template>
