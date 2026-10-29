<script setup lang="ts">
import {
    QUIZ_STRINGS,
    type BlankAnswerState,
    type QuizBlankData,
    type YesNoCandidate,
} from "@/lib/quizUtils";

const props = defineProps<{
    blanks: QuizBlankData[];
    candidatesByBlank: Record<number, YesNoCandidate | null>;
    blankStates: Record<number, BlankAnswerState>;
}>();

const emit = defineEmits<{
    answer: [blankPosition: number, answeredYes: boolean];
}>();

function buttonClasses(blankPosition: number, isYesButton: boolean): string {
    const state = props.blankStates[blankPosition];
    const candidate = props.candidatesByBlank[blankPosition];

    if (!state?.answered || !candidate) {
        return "border-neutral-200 bg-white text-neutral-900 hover:border-neutral-400 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-neutral-800";
    }

    const correctChoiceIsYes = candidate.isCorrect;
    const isCorrectButton = isYesButton === correctChoiceIsYes;
    const userChoseYes = state.userAnswer.endsWith("(Yes)");
    const isChosenButton = isYesButton === userChoseYes;

    if (isCorrectButton) {
        return "border-emerald-600 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200";
    }

    if (isChosenButton && !isCorrectButton) {
        return "border-red-600 bg-red-50 text-red-900 dark:border-red-500 dark:bg-red-950/60 dark:text-red-200";
    }

    return "border-neutral-200 bg-neutral-50 text-neutral-400 opacity-60 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-500";
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-for="blank in blanks"
            :key="blank.position"
            class="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 dark:border-neutral-800 dark:bg-neutral-900/50"
        >
            <p
                v-if="blanks.length > 1"
                class="text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400"
            >
                {{ QUIZ_STRINGS.blankLabel(blank.position, blanks.length) }}
            </p>

            <p
                v-if="blankStates[blank.position]?.skipped || !candidatesByBlank[blank.position]"
                class="text-sm text-neutral-500 italic"
            >
                {{ QUIZ_STRINGS.blankSkippedNotice }}
            </p>

            <template v-else>
                <p class="text-sm text-neutral-800 sm:text-base dark:text-neutral-200">
                    {{ QUIZ_STRINGS.yesNoPromptPrefix }}
                    <strong
                        class="rounded bg-neutral-200/80 px-1.5 py-0.5 font-semibold text-neutral-950 dark:bg-neutral-800 dark:text-neutral-50"
                    >
                        {{ candidatesByBlank[blank.position]?.text }}
                    </strong>
                    {{ QUIZ_STRINGS.yesNoPromptSuffix }}
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        :disabled="blankStates[blank.position]?.answered === true"
                        class="flex min-h-11 items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-semibold transition focus:ring-2 focus:ring-neutral-900 focus:outline-none disabled:cursor-default dark:focus:ring-neutral-100"
                        :class="buttonClasses(blank.position, true)"
                        @click="emit('answer', blank.position, true)"
                    >
                        {{ QUIZ_STRINGS.yesButton }}
                    </button>

                    <button
                        type="button"
                        :disabled="blankStates[blank.position]?.answered === true"
                        class="flex min-h-11 items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-semibold transition focus:ring-2 focus:ring-neutral-900 focus:outline-none disabled:cursor-default dark:focus:ring-neutral-100"
                        :class="buttonClasses(blank.position, false)"
                        @click="emit('answer', blank.position, false)"
                    >
                        {{ QUIZ_STRINGS.noButton }}
                    </button>
                </div>

                <div
                    v-if="blankStates[blank.position]?.answered"
                    class="flex flex-wrap items-center gap-2 text-xs font-medium"
                    :class="
                        blankStates[blank.position]?.isCorrect
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-red-700 dark:text-red-300'
                    "
                >
                    <span>
                        {{
                            blankStates[blank.position]?.isCorrect
                                ? `✓ ${QUIZ_STRINGS.statusCorrect}`
                                : `✗ ${QUIZ_STRINGS.statusIncorrect}`
                        }}
                    </span>

                    <span
                        v-if="!blankStates[blank.position]?.isCorrect"
                        class="text-neutral-700 dark:text-neutral-300"
                    >
                        {{
                            (blankStates[blank.position]?.correctAnswers.length ?? 0) > 1
                                ? QUIZ_STRINGS.correctAnswersLabel
                                : QUIZ_STRINGS.correctAnswerLabel
                        }}
                        <strong class="font-semibold">
                            {{ blankStates[blank.position]?.correctAnswers.join(" / ") }}
                        </strong>
                    </span>
                </div>
            </template>
        </div>
    </div>
</template>
