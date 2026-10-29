<script setup lang="ts">
import { computed } from "vue";
import {
    parseSentenceSegments,
    type BlankAnswerState,
    type QuizBlankData,
    type SentenceSegment,
} from "@/lib/quizUtils";

const props = defineProps<{
    sentence: string;
    blanks: QuizBlankData[];
    blankStates: Record<number, BlankAnswerState>;
}>();

const segments = computed<SentenceSegment[]>(() =>
    parseSentenceSegments(props.sentence, props.blanks),
);

function getBlankState(blankPosition: number): BlankAnswerState | undefined {
    return props.blankStates[blankPosition];
}
</script>

<template>
    <p
        class="text-base leading-relaxed font-medium break-words text-neutral-900 sm:text-lg dark:text-neutral-100"
    >
        <template v-for="(segment, index) in segments" :key="index">
            <span v-if="segment.type === 'text'">{{ segment.text }}</span>

            <span
                v-else-if="getBlankState(segment.blankPosition)?.answered"
                class="mx-1 inline-flex items-center gap-1 rounded-md border px-2.5 py-0.5 text-sm font-semibold align-baseline"
                :class="
                    getBlankState(segment.blankPosition)?.isCorrect
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-300'
                        : 'border-red-600 bg-red-50 text-red-800 dark:border-red-500 dark:bg-red-950/60 dark:text-red-300'
                "
            >
                <span aria-hidden="true">
                    {{ getBlankState(segment.blankPosition)?.isCorrect ? "✓" : "✗" }}
                </span>
                <span>{{ getBlankState(segment.blankPosition)?.userAnswer }}</span>
            </span>

            <span
                v-else
                class="mx-1 inline-block min-w-20 rounded border-b-2 border-dashed border-neutral-400 bg-neutral-100 px-3 py-0.5 text-center text-xs font-semibold text-neutral-500 align-baseline dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                :aria-label="`Blank ${segment.blankPosition}`"
            >
                {{ blanks.length > 1 ? `(${segment.blankPosition})` : "____" }}
            </span>
        </template>
    </p>
</template>
