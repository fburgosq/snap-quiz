<script setup lang="ts">
import { Button } from "@/components/ui/button";
import {
    parseSentenceSegments,
    QUIZ_STRINGS,
    type BlankAnswerState,
    type ItemResultSummary,
    type SentenceSegment,
} from "@/lib/quizUtils";

defineProps<{
    title: string;
    score: {
        correctCount: number;
        totalCount: number;
        percentage: number;
    };
    summaries: ItemResultSummary[];
}>();

const emit = defineEmits<{
    tryAgain: [];
    changeMode: [];
}>();

function getSegments(summary: ItemResultSummary): SentenceSegment[] {
    return parseSentenceSegments(summary.item.sentence, summary.item.blanks);
}

function findBlankState(
    summary: ItemResultSummary,
    blankPosition: number,
): BlankAnswerState | undefined {
    return summary.blanks.find((b) => b.blankPosition === blankPosition);
}
</script>

<template>
    <div class="space-y-6">
        <div
            class="rounded-xl border border-neutral-200 bg-neutral-50 p-5 text-center dark:border-neutral-800 dark:bg-neutral-900"
        >
            <p
                class="text-xs font-semibold tracking-wider text-neutral-500 uppercase dark:text-neutral-400"
            >
                {{ QUIZ_STRINGS.resultsHeading }}
            </p>
            <h2 class="mt-1 text-xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ title }}
            </h2>
            <p class="mt-2 text-lg font-semibold text-neutral-800 dark:text-neutral-200">
                {{
                    QUIZ_STRINGS.scoreSummary(
                        score.correctCount,
                        score.totalCount,
                        score.percentage,
                    )
                }}
            </p>
        </div>

        <ol class="space-y-4">
            <li
                v-for="summary in summaries"
                :key="summary.item.position"
                class="rounded-xl border p-4"
                :class="
                    summary.isCorrect
                        ? 'border-emerald-300 bg-emerald-50/40 dark:border-emerald-800/70 dark:bg-emerald-950/20'
                        : 'border-red-300 bg-red-50/40 dark:border-red-800/70 dark:bg-red-950/20'
                "
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                        #{{ summary.item.position }}
                    </span>

                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="
                            summary.isCorrect
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200'
                        "
                    >
                        <span aria-hidden="true">{{ summary.isCorrect ? "✓" : "✗" }}</span>
                        <span>
                            {{
                                summary.isCorrect
                                    ? QUIZ_STRINGS.statusCorrect
                                    : QUIZ_STRINGS.statusIncorrect
                            }}
                        </span>
                    </span>
                </div>

                <p
                    class="text-sm leading-relaxed break-words text-neutral-900 sm:text-base dark:text-neutral-100"
                >
                    <template v-for="(segment, segIndex) in getSegments(summary)" :key="segIndex">
                        <span v-if="segment.type === 'text'">{{ segment.text }}</span>

                        <span
                            v-else
                            class="mx-1 inline-flex flex-wrap items-center gap-1 align-baseline"
                        >
                            <span
                                class="rounded px-2 py-0.5 text-xs font-semibold sm:text-sm"
                                :class="
                                    findBlankState(summary, segment.blankPosition)?.isCorrect
                                        ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200'
                                        : 'bg-red-100 text-red-900 line-through dark:bg-red-900/60 dark:text-red-200'
                                "
                            >
                                {{
                                    findBlankState(summary, segment.blankPosition)?.userAnswer ||
                                    "—"
                                }}
                            </span>

                            <span
                                v-if="
                                    !findBlankState(summary, segment.blankPosition)?.isCorrect &&
                                    (findBlankState(summary, segment.blankPosition)?.correctAnswers
                                        .length ?? 0) > 0
                                "
                                class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900 sm:text-sm dark:bg-emerald-900/60 dark:text-emerald-200"
                            >
                                →
                                {{
                                    findBlankState(
                                        summary,
                                        segment.blankPosition,
                                    )?.correctAnswers.join(" / ")
                                }}
                            </span>
                        </span>
                    </template>
                </p>

                <p
                    v-if="summary.item.reason"
                    class="mt-2 text-sm text-neutral-600 dark:text-neutral-400"
                >
                    {{ summary.item.reason }}
                </p>
            </li>
        </ol>

        <div class="flex flex-col gap-3 sm:flex-row">
            <Button type="button" class="min-h-11 flex-1" @click="emit('tryAgain')">
                {{ QUIZ_STRINGS.tryAgainButton }}
            </Button>

            <Button
                type="button"
                variant="outline"
                class="min-h-11 flex-1"
                @click="emit('changeMode')"
            >
                {{ QUIZ_STRINGS.changeModeButton }}
            </Button>
        </div>
    </div>
</template>
