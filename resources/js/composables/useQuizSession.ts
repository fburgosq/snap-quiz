import { computed, ref, toValue, type MaybeRefOrGetter } from "vue";
import {
    buildMultipleChoiceOptions,
    buildYesNoCandidate,
    getCorrectTexts,
    isAnswerCorrect,
    type BlankAnswerState,
    type ItemResultSummary,
    type QuizBlankData,
    type QuizData,
    type QuizItemData,
    type QuizMode,
    type QuizOptionData,
    type QuizPhase,
    type YesNoCandidate,
} from "@/lib/quizUtils";

/**
 * Trade-off note:
 * In this version, quiz option data (including `is_correct`) is delivered to the client
 * by design so that answer evaluation is instantaneous and works entirely in memory.
 */
export function useQuizSession(quizSource: MaybeRefOrGetter<QuizData>) {
    const phase = ref<QuizPhase>("mode");
    const mode = ref<QuizMode | null>(null);
    const currentIndex = ref<number>(0);

    const multipleChoiceMap = ref<Record<string, QuizOptionData[]>>({});
    const yesNoMap = ref<Record<string, YesNoCandidate | null>>({});
    const answersMap = ref<Record<string, BlankAnswerState>>({});

    const orderedItems = computed<QuizItemData[]>(() => {
        const quiz = toValue(quizSource);

        return [...(quiz.items ?? [])].sort((a, b) => a.position - b.position);
    });

    const totalQuestions = computed<number>(() => orderedItems.value.length);

    const currentItem = computed<QuizItemData | null>(
        () => orderedItems.value[currentIndex.value] ?? null,
    );

    const isLastQuestion = computed<boolean>(
        () => totalQuestions.value > 0 && currentIndex.value >= totalQuestions.value - 1,
    );

    function blankKey(itemPosition: number, blankPosition: number): string {
        return `${itemPosition}:${blankPosition}`;
    }

    function normalizeItemBlanks(item: QuizItemData): QuizBlankData[] {
        if (item.blanks && item.blanks.length > 0) {
            return [...item.blanks].sort((a, b) => a.position - b.position);
        }

        return [{ position: 1, options: [] }];
    }

    function initializeSessionState(): void {
        const nextMcMap: Record<string, QuizOptionData[]> = {};
        const nextYesNoMap: Record<string, YesNoCandidate | null> = {};
        const nextAnswersMap: Record<string, BlankAnswerState> = {};

        for (const item of orderedItems.value) {
            const blanks = normalizeItemBlanks(item);

            for (const blank of blanks) {
                const key = blankKey(item.position, blank.position);
                const correctAnswers = getCorrectTexts(blank);

                if (blank.options.length === 0 || correctAnswers.length === 0) {
                    console.warn(
                        `[SnapQuiz] Blank ${blank.position} in item ${item.position} has no valid correct options; skipping evaluation.`,
                    );

                    nextMcMap[key] = [];
                    nextYesNoMap[key] = null;
                    nextAnswersMap[key] = {
                        blankPosition: blank.position,
                        answered: true,
                        isCorrect: true,
                        userAnswer: "—",
                        correctAnswers: [],
                        skipped: true,
                    };
                    continue;
                }

                nextMcMap[key] = buildMultipleChoiceOptions(blank.options);
                nextYesNoMap[key] = buildYesNoCandidate(blank.options);
                nextAnswersMap[key] = {
                    blankPosition: blank.position,
                    answered: false,
                    isCorrect: false,
                    userAnswer: "",
                    correctAnswers,
                    skipped: false,
                };
            }
        }

        multipleChoiceMap.value = nextMcMap;
        yesNoMap.value = nextYesNoMap;
        answersMap.value = nextAnswersMap;
        currentIndex.value = 0;
    }

    function selectMode(chosenMode: QuizMode): void {
        mode.value = chosenMode;
        initializeSessionState();
        phase.value = "question";
    }

    function getBlankState(
        itemPosition: number,
        blankPosition: number,
    ): BlankAnswerState | undefined {
        return answersMap.value[blankKey(itemPosition, blankPosition)];
    }

    function getMultipleChoiceOptions(
        itemPosition: number,
        blankPosition: number,
    ): QuizOptionData[] {
        return multipleChoiceMap.value[blankKey(itemPosition, blankPosition)] ?? [];
    }

    function getYesNoCandidate(itemPosition: number, blankPosition: number): YesNoCandidate | null {
        return yesNoMap.value[blankKey(itemPosition, blankPosition)] ?? null;
    }

    const currentItemBlanks = computed<QuizBlankData[]>(() => {
        if (!currentItem.value) {
            return [];
        }

        return normalizeItemBlanks(currentItem.value);
    });

    const currentBlankStates = computed<Record<number, BlankAnswerState>>(() => {
        const item = currentItem.value;

        if (!item) {
            return {};
        }

        const states: Record<number, BlankAnswerState> = {};

        for (const blank of currentItemBlanks.value) {
            const state = getBlankState(item.position, blank.position);

            if (state) {
                states[blank.position] = state;
            }
        }

        return states;
    });

    const isCurrentItemComplete = computed<boolean>(() => {
        const blanks = currentItemBlanks.value;

        if (blanks.length === 0) {
            return false;
        }

        return blanks.every((blank) => currentBlankStates.value[blank.position]?.answered === true);
    });

    const isCurrentItemCorrect = computed<boolean>(() => {
        if (!isCurrentItemComplete.value) {
            return false;
        }

        return currentItemBlanks.value.every(
            (blank) => currentBlankStates.value[blank.position]?.isCorrect === true,
        );
    });

    function answerMultipleChoice(blankPosition: number, option: QuizOptionData): void {
        const item = currentItem.value;

        if (!item) {
            return;
        }

        const key = blankKey(item.position, blankPosition);
        const existing = answersMap.value[key];

        if (!existing || existing.answered) {
            return;
        }

        answersMap.value = {
            ...answersMap.value,
            [key]: {
                ...existing,
                answered: true,
                isCorrect: option.is_correct,
                userAnswer: option.text,
            },
        };
    }

    function validateOpenAnswer(inputsByBlankPosition: Record<number, string>): void {
        const item = currentItem.value;

        if (!item) {
            return;
        }

        const updated: Record<string, BlankAnswerState> = { ...answersMap.value };

        for (const blank of currentItemBlanks.value) {
            const key = blankKey(item.position, blank.position);
            const existing = updated[key];

            if (!existing || existing.answered) {
                continue;
            }

            const rawInput = (inputsByBlankPosition[blank.position] ?? "").trim();

            if (rawInput === "") {
                return;
            }
        }

        for (const blank of currentItemBlanks.value) {
            const key = blankKey(item.position, blank.position);
            const existing = updated[key];

            if (!existing || existing.answered) {
                continue;
            }

            const rawInput = (inputsByBlankPosition[blank.position] ?? "").trim();
            const correct = isAnswerCorrect(rawInput, existing.correctAnswers);

            updated[key] = {
                ...existing,
                answered: true,
                isCorrect: correct,
                userAnswer: rawInput,
            };
        }

        answersMap.value = updated;
    }

    function answerYesNo(blankPosition: number, answeredYes: boolean): void {
        const item = currentItem.value;

        if (!item) {
            return;
        }

        const key = blankKey(item.position, blankPosition);
        const existing = answersMap.value[key];
        const candidate = getYesNoCandidate(item.position, blankPosition);

        if (!existing || existing.answered || !candidate) {
            return;
        }

        const correct = answeredYes === candidate.isCorrect;

        answersMap.value = {
            ...answersMap.value,
            [key]: {
                ...existing,
                answered: true,
                isCorrect: correct,
                userAnswer: `${candidate.text} (${answeredYes ? "Yes" : "No"})`,
            },
        };
    }

    function nextQuestion(): void {
        if (!isCurrentItemComplete.value) {
            return;
        }

        if (isLastQuestion.value) {
            // TODO: Persist quiz attempt and user answers to the backend when attempt tracking is added.
            phase.value = "results";

            return;
        }

        currentIndex.value += 1;
    }

    function tryAgain(): void {
        if (!mode.value) {
            phase.value = "mode";

            return;
        }

        initializeSessionState();
        phase.value = "question";
    }

    function changeMode(): void {
        mode.value = null;
        currentIndex.value = 0;
        phase.value = "mode";
    }

    const itemSummaries = computed<ItemResultSummary[]>(() =>
        orderedItems.value.map((item) => {
            const blanks = normalizeItemBlanks(item);
            const blankStates = blanks.map(
                (blank) =>
                    getBlankState(item.position, blank.position) ?? {
                        blankPosition: blank.position,
                        answered: false,
                        isCorrect: false,
                        userAnswer: "",
                        correctAnswers: getCorrectTexts(blank),
                        skipped: false,
                    },
            );

            const itemCorrect =
                blankStates.length > 0 &&
                blankStates.every((state) => state.answered && state.isCorrect);

            return {
                item,
                isCorrect: itemCorrect,
                blanks: blankStates,
            };
        }),
    );

    const score = computed(() => {
        const totalCount = itemSummaries.value.length;
        const correctCount = itemSummaries.value.filter((summary) => summary.isCorrect).length;
        const percentage = totalCount > 0 ? Math.round((correctCount / totalCount) * 100) : 0;

        return {
            correctCount,
            totalCount,
            percentage,
        };
    });

    return {
        phase,
        mode,
        currentIndex,
        totalQuestions,
        orderedItems,
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
    };
}
