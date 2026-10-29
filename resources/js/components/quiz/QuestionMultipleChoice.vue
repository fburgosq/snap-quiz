<script setup lang="ts">
import {
    QUIZ_STRINGS,
    type BlankAnswerState,
    type QuizBlankData,
    type QuizOptionData,
} from "@/lib/quizUtils";

const props = defineProps<{
    blanks: QuizBlankData[];
    optionsByBlank: Record<number, QuizOptionData[]>;
    blankStates: Record<number, BlankAnswerState>;
}>();

const emit = defineEmits<{
    answer: [blankPosition: number, option: QuizOptionData];
}>();

function isChosenOption(blankPosition: number, option: QuizOptionData): boolean {
    const state = props.blankStates[blankPosition];

    return state?.answered === true && state.userAnswer === option.text;
}

function optionClasses(blankPosition: number, option: QuizOptionData): string {
    const state = props.blankStates[blankPosition];

    if (!state?.answered) {
        return "border-neutral-200 bg-white text-neutral-900 hover:border-neutral-400 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-neutral-800";
    }

    if (option.is_correct) {
        return "border-emerald-600 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200";
    }

    if (isChosenOption(blankPosition, option) && !option.is_correct) {
        return "border-red-600 bg-red-50 text-red-900 dark:border-red-500 dark:bg-red-950/60 dark:text-red-200";
    }

    return "border-neutral-200 bg-neutral-50 text-neutral-400 opacity-60 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-500";
}
</script>

<template>
    <div class="space-y-5">
        <div v-for="blank in blanks" :key="blank.position" class="space-y-2.5">
            <p
                v-if="blanks.length > 1"
                class="text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400"
            >
                {{ QUIZ_STRINGS.blankLabel(blank.position, blanks.length) }}
            </p>

            <p v-if="blankStates[blank.position]?.skipped" class="text-sm text-neutral-500 italic">
                {{ QUIZ_STRINGS.blankSkippedNotice }}
            </p>

            <div v-else class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <button
                    v-for="(option, index) in optionsByBlank[blank.position] ?? []"
                    :key="`${blank.position}-${index}-${option.text}`"
                    type="button"
                    :disabled="blankStates[blank.position]?.answered === true"
                    class="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg border px-4 py-2.5 text-left text-sm font-medium transition focus:ring-2 focus:ring-neutral-900 focus:outline-none disabled:cursor-default dark:focus:ring-neutral-100"
                    :class="optionClasses(blank.position, option)"
                    @click="emit('answer', blank.position, option)"
                >
                    <span class="break-words">{{ option.text }}</span>

                    <span
                        v-if="blankStates[blank.position]?.answered && option.is_correct"
                        class="shrink-0 text-xs font-semibold"
                    >
                        ✓ {{ QUIZ_STRINGS.statusCorrect }}
                    </span>

                    <span
                        v-else-if="
                            blankStates[blank.position]?.answered &&
                            isChosenOption(blank.position, option) &&
                            !option.is_correct
                        "
                        class="shrink-0 text-xs font-semibold"
                    >
                        ✗ {{ QUIZ_STRINGS.statusIncorrect }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
