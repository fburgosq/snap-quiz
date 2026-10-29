<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue";
import FeedbackPanel from "@/components/quiz/FeedbackPanel.vue";
import ModeSelector from "@/components/quiz/ModeSelector.vue";
import QuestionMultipleChoice from "@/components/quiz/QuestionMultipleChoice.vue";
import QuestionOpenAnswer from "@/components/quiz/QuestionOpenAnswer.vue";
import QuestionYesNo from "@/components/quiz/QuestionYesNo.vue";
import QuizProgress from "@/components/quiz/QuizProgress.vue";
import QuizResults from "@/components/quiz/QuizResults.vue";
import SentenceDisplay from "@/components/quiz/SentenceDisplay.vue";
import { useQuizSession } from "@/composables/useQuizSession";
import {
    QUIZ_STRINGS,
    type QuizData,
    type QuizOptionData,
    type YesNoCandidate,
} from "@/lib/quizUtils";

const props = defineProps<{
    quiz: QuizData;
}>();

const {
    phase,
    mode,
    currentIndex,
    totalQuestions,
    currentItem,
    currentItemBlanks,
    currentBlankStates,
    isCurrentItemComplete,
    isCurrentItemCorrect,
    isLastQuestion,
    itemSummaries,
    score,
    selectMode,
    getMultipleChoiceOptions,
    getYesNoCandidate,
    answerMultipleChoice,
    validateOpenAnswer,
    answerYesNo,
    nextQuestion,
    tryAgain,
    changeMode,
} = useQuizSession(() => props.quiz);

const questionContainerRef = ref<HTMLElement | null>(null);
const nextButtonRef = ref<HTMLButtonElement | null>(null);

const currentMultipleChoiceOptions = computed<Record<number, QuizOptionData[]>>(() => {
    const item = currentItem.value;

    if (!item) {
        return {};
    }

    const map: Record<number, QuizOptionData[]> = {};

    for (const blank of currentItemBlanks.value) {
        map[blank.position] = getMultipleChoiceOptions(item.position, blank.position);
    }

    return map;
});

const currentYesNoCandidates = computed<Record<number, YesNoCandidate | null>>(() => {
    const item = currentItem.value;

    if (!item) {
        return {};
    }

    const map: Record<number, YesNoCandidate | null> = {};

    for (const blank of currentItemBlanks.value) {
        map[blank.position] = getYesNoCandidate(item.position, blank.position);
    }

    return map;
});

watch(isCurrentItemComplete, async (complete) => {
    if (complete) {
        await nextTick();
        nextButtonRef.value?.focus();
    }
});

watch([currentIndex, phase], async ([newIndex, newPhase]) => {
    if (newPhase === "question" && newIndex >= 0) {
        await nextTick();
        const firstControl = questionContainerRef.value?.querySelector<HTMLElement>(
            "input:not([disabled]), button:not([disabled])",
        );
        firstControl?.focus();
    }
});
</script>

<template>
    <div
        class="mx-auto w-full max-w-2xl rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs sm:p-8 dark:border-neutral-800 dark:bg-neutral-900"
    >
        <!-- Empty state for 0 items -->
        <div v-if="totalQuestions === 0" class="py-8 text-center">
            <h1 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                {{ quiz.title || QUIZ_STRINGS.emptyQuizTitle }}
            </h1>
            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                {{ QUIZ_STRINGS.emptyQuizMessage }}
            </p>
        </div>

        <!-- Phase 1: Mode Selection -->
        <div v-else-if="phase === 'mode'" class="space-y-6">
            <h1 class="text-xl font-bold text-neutral-900 sm:text-2xl dark:text-neutral-100">
                {{ quiz.title }}
            </h1>

            <ModeSelector @select="selectMode" />
        </div>

        <!-- Phase 2: Questions -->
        <div
            v-else-if="phase === 'question' && currentItem"
            ref="questionContainerRef"
            class="space-y-6"
        >
            <div class="space-y-3">
                <h1 class="text-lg font-bold text-neutral-900 sm:text-xl dark:text-neutral-100">
                    {{ quiz.title }}
                </h1>

                <QuizProgress :current="currentIndex + 1" :total="totalQuestions" />
            </div>

            <div
                class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 sm:p-5 dark:border-neutral-800 dark:bg-neutral-950/60"
            >
                <SentenceDisplay
                    :sentence="currentItem.sentence"
                    :blanks="currentItemBlanks"
                    :blank-states="currentBlankStates"
                />
            </div>

            <QuestionMultipleChoice
                v-if="mode === 'multiple_choice'"
                :blanks="currentItemBlanks"
                :options-by-blank="currentMultipleChoiceOptions"
                :blank-states="currentBlankStates"
                @answer="answerMultipleChoice"
            />

            <QuestionOpenAnswer
                v-else-if="mode === 'open_answer'"
                :item-position="currentItem.position"
                :blanks="currentItemBlanks"
                :blank-states="currentBlankStates"
                @validate="validateOpenAnswer"
            />

            <QuestionYesNo
                v-else-if="mode === 'yes_no'"
                :blanks="currentItemBlanks"
                :candidates-by-blank="currentYesNoCandidates"
                :blank-states="currentBlankStates"
                @answer="answerYesNo"
            />

            <FeedbackPanel
                :visible="isCurrentItemComplete"
                :is-correct="isCurrentItemCorrect"
                :reason="currentItem.reason"
            />

            <div v-if="isCurrentItemComplete" class="flex justify-end">
                <button
                    ref="nextButtonRef"
                    type="button"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-neutral-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2 focus:outline-none sm:w-auto dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200 dark:focus:ring-neutral-100"
                    @click="nextQuestion"
                >
                    {{ isLastQuestion ? QUIZ_STRINGS.finishButton : QUIZ_STRINGS.nextButton }}
                </button>
            </div>
        </div>

        <!-- Phase 3: Results -->
        <QuizResults
            v-else-if="phase === 'results'"
            :title="quiz.title"
            :score="score"
            :summaries="itemSummaries"
            @try-again="tryAgain"
            @change-mode="changeMode"
        />
    </div>
</template>
