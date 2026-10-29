export type QuizMode = "multiple_choice" | "open_answer" | "yes_no";
export type QuizPhase = "mode" | "question" | "results";

export interface QuizOptionData {
    text: string;
    is_correct: boolean;
}

export interface QuizBlankData {
    position: number;
    options: QuizOptionData[];
}

export interface QuizItemData {
    position: number;
    sentence: string;
    blank_count?: number;
    reason: string;
    blanks: QuizBlankData[];
}

export interface QuizData {
    uuid: string;
    title: string;
    created_at?: string | null;
    duplicate?: boolean;
    items: QuizItemData[];
}

export type SentenceSegment =
    | { type: "text"; text: string }
    | { type: "blank"; blankPosition: number; blankIndex: number };

export interface YesNoCandidate {
    text: string;
    isCorrect: boolean;
}

export interface BlankAnswerState {
    blankPosition: number;
    answered: boolean;
    isCorrect: boolean;
    userAnswer: string;
    correctAnswers: string[];
    skipped: boolean;
}

export interface ItemResultSummary {
    item: QuizItemData;
    isCorrect: boolean;
    blanks: BlankAnswerState[];
}

export const QUIZ_STRINGS = {
    emptyQuizTitle: "No questions available",
    emptyQuizMessage: "This quiz does not contain any fill-in-the-blank exercises yet.",
    chooseModeHeading: "Choose how to play",
    chooseModeSubheading: "Your selected mode applies to all questions in this quiz.",
    modes: {
        multiple_choice: {
            title: "Multiple choice",
            description: "Pick the right word from four shuffled options for each blank.",
        },
        open_answer: {
            title: "Open answer",
            description: "Type the missing word yourself and validate your answer.",
        },
        yes_no: {
            title: "Yes / No",
            description: "Decide whether a proposed word is the right answer for each blank.",
        },
    },
    questionProgress: (current: number, total: number): string => `Question ${current} of ${total}`,
    progressAriaLabel: "Quiz progress",
    blankLabel: (position: number, totalBlanks: number): string =>
        totalBlanks > 1 ? `Blank ${position}` : "Your answer",
    openAnswerPlaceholder: "Type your answer...",
    validateButton: "Validate",
    yesNoPromptPrefix: "Is",
    yesNoPromptSuffix: "the correct answer?",
    yesButton: "Yes",
    noButton: "No",
    statusCorrect: "Correct",
    statusIncorrect: "Incorrect",
    correctAnswerLabel: "Correct answer:",
    correctAnswersLabel: "Correct answers:",
    nextButton: "Next",
    finishButton: "Finish",
    resultsHeading: "Quiz Results",
    scoreSummary: (correct: number, total: number, percentage: number): string =>
        `${correct} of ${total} correct (${percentage}%)`,
    tryAgainButton: "Try again",
    changeModeButton: "Change mode",
    blankSkippedNotice: "No options available for this blank.",
} as const;

/**
 * Return a new shuffled array using Fisher-Yates without mutating the input.
 */
export function shuffle<T>(items: readonly T[], randomFn: () => number = Math.random): T[] {
    const copy = [...items];

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(randomFn() * (i + 1));
        const temp = copy[i];
        copy[i] = copy[j];
        copy[j] = temp;
    }

    return copy;
}

/**
 * Normalize an answer for comparison:
 * trim, strip trailing periods, remove diacritics (NFD), collapse inner whitespace, and lowercase.
 */
export function normalizeAnswer(input: string): string {
    return input
        .trim()
        .replace(/\.+$/, "")
        .trim()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ")
        .toLowerCase();
}

/**
 * Check whether userInput matches any of the valid correct options.
 */
export function isAnswerCorrect(
    userInput: string,
    correctOptions: readonly (string | QuizOptionData)[],
): boolean {
    const normalizedInput = normalizeAnswer(userInput);

    if (normalizedInput === "") {
        return false;
    }

    return correctOptions.some((option) => {
        if (typeof option !== "string" && !option.is_correct) {
            return false;
        }

        const text = typeof option === "string" ? option : option.text;

        return normalizeAnswer(text) === normalizedInput;
    });
}

/**
 * Build up to 4 shuffled multiple-choice options:
 * 1 randomly chosen correct option + up to 3 randomly chosen incorrect options.
 */
export function buildMultipleChoiceOptions(
    options: readonly QuizOptionData[],
    randomFn: () => number = Math.random,
): QuizOptionData[] {
    const correctPool = shuffle(
        options.filter((opt) => opt.is_correct),
        randomFn,
    );
    const incorrectPool = shuffle(
        options.filter((opt) => !opt.is_correct),
        randomFn,
    );

    if (correctPool.length === 0) {
        return [];
    }

    const selected: QuizOptionData[] = [correctPool[0], ...incorrectPool.slice(0, 3)];

    return shuffle(selected, randomFn);
}

/**
 * Build a Yes/No candidate with 50% probability of picking a correct vs incorrect option.
 */
export function buildYesNoCandidate(
    options: readonly QuizOptionData[],
    randomFn: () => number = Math.random,
): YesNoCandidate | null {
    const correctPool = shuffle(
        options.filter((opt) => opt.is_correct),
        randomFn,
    );
    const incorrectPool = shuffle(
        options.filter((opt) => !opt.is_correct),
        randomFn,
    );

    if (correctPool.length === 0 && incorrectPool.length === 0) {
        return null;
    }

    if (incorrectPool.length === 0) {
        return { text: correctPool[0].text, isCorrect: true };
    }

    if (correctPool.length === 0) {
        return { text: incorrectPool[0].text, isCorrect: false };
    }

    const pickCorrect = randomFn() < 0.5;

    return pickCorrect
        ? { text: correctPool[0].text, isCorrect: true }
        : { text: incorrectPool[0].text, isCorrect: false };
}

/**
 * Split a sentence by "____" into ordered text and blank segments.
 * If a sentence contains no "____", render it as plain text with one blank at the end.
 */
export function parseSentenceSegments(
    sentence: string,
    blanks: readonly QuizBlankData[],
): SentenceSegment[] {
    const parts = sentence.split("____");

    if (parts.length === 1) {
        const fallbackPosition = blanks[0]?.position ?? 1;

        return [
            { type: "text", text: sentence },
            { type: "blank", blankPosition: fallbackPosition, blankIndex: 0 },
        ];
    }

    const segments: SentenceSegment[] = [];

    parts.forEach((part, index) => {
        if (part !== "") {
            segments.push({ type: "text", text: part });
        }

        if (index < parts.length - 1) {
            const blankPosition = blanks[index]?.position ?? index + 1;
            segments.push({
                type: "blank",
                blankPosition,
                blankIndex: index,
            });
        }
    });

    return segments;
}

/**
 * Return all correct option texts for a blank.
 */
export function getCorrectTexts(blank: QuizBlankData): string[] {
    return blank.options.filter((opt) => opt.is_correct).map((opt) => opt.text);
}
