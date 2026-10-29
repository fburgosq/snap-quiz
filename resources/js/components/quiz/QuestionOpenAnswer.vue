<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Button } from "@/components/ui/button";
import { QUIZ_STRINGS, type BlankAnswerState, type QuizBlankData } from "@/lib/quizUtils";

const props = defineProps<{
    itemPosition: number;
    blanks: QuizBlankData[];
    blankStates: Record<number, BlankAnswerState>;
}>();

const emit = defineEmits<{
    validate: [inputs: Record<number, string>];
}>();

const inputs = ref<Record<number, string>>({});

function resetInputs(): void {
    const next: Record<number, string> = {};

    for (const blank of props.blanks) {
        next[blank.position] = props.blankStates[blank.position]?.userAnswer ?? "";
    }

    inputs.value = next;
}

watch(
    () => props.itemPosition,
    () => {
        resetInputs();
    },
    { immediate: true },
);

const isAllLocked = computed<boolean>(() =>
    props.blanks.every((blank) => props.blankStates[blank.position]?.answered === true),
);

const canValidate = computed<boolean>(() => {
    if (isAllLocked.value) {
        return false;
    }

    return props.blanks.every((blank) => {
        if (props.blankStates[blank.position]?.answered) {
            return true;
        }

        return (inputs.value[blank.position] ?? "").trim() !== "";
    });
});

function handleSubmit(): void {
    if (!canValidate.value) {
        return;
    }

    emit("validate", { ...inputs.value });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="handleSubmit">
        <div v-for="blank in blanks" :key="blank.position" class="space-y-1.5">
            <label
                :for="`open-answer-blank-${blank.position}`"
                class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300"
            >
                {{ QUIZ_STRINGS.blankLabel(blank.position, blanks.length) }}
            </label>

            <p v-if="blankStates[blank.position]?.skipped" class="text-sm text-neutral-500 italic">
                {{ QUIZ_STRINGS.blankSkippedNotice }}
            </p>

            <template v-else>
                <input
                    :id="`open-answer-blank-${blank.position}`"
                    v-model="inputs[blank.position]"
                    type="text"
                    :disabled="blankStates[blank.position]?.answered === true"
                    :placeholder="QUIZ_STRINGS.openAnswerPlaceholder"
                    autocomplete="off"
                    class="min-h-11 w-full rounded-lg border px-3.5 py-2 text-sm transition focus:ring-2 focus:outline-none disabled:cursor-default"
                    :class="
                        !blankStates[blank.position]?.answered
                            ? 'border-neutral-300 bg-white text-neutral-900 focus:ring-neutral-900 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:focus:ring-neutral-100'
                            : blankStates[blank.position]?.isCorrect
                              ? 'border-emerald-600 bg-emerald-50 text-emerald-900 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200'
                              : 'border-red-600 bg-red-50 text-red-900 dark:border-red-500 dark:bg-red-950/60 dark:text-red-200'
                    "
                />

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

        <Button
            v-if="!isAllLocked"
            type="submit"
            class="min-h-11 w-full sm:w-auto"
            :disabled="!canValidate"
        >
            {{ QUIZ_STRINGS.validateButton }}
        </Button>
    </form>
</template>
