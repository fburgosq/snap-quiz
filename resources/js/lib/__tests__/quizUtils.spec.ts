import { describe, expect, it } from "vitest";
import { useQuizSession } from "@/composables/useQuizSession";
import {
    buildMultipleChoiceOptions,
    buildYesNoCandidate,
    getCorrectTexts,
    isAnswerCorrect,
    normalizeAnswer,
    parseSentenceSegments,
    shuffle,
} from "@/lib/quizUtils";
import type { QuizBlankData, QuizData } from "@/lib/quizUtils";

const sampleBlank: QuizBlankData = {
    position: 1,
    options: [
        { text: "went", is_correct: true },
        { text: "had gone", is_correct: true },
        { text: "go", is_correct: false },
        { text: "goes", is_correct: false },
        { text: "going", is_correct: false },
        { text: "gone", is_correct: false },
    ],
};

describe("quizUtils", () => {
    it("normalizes answers by trimming, lowercasing, collapsing whitespace, removing diacritics, and stripping trailing periods", () => {
        expect(normalizeAnswer("  Had   Gone...  ")).toBe("had gone");
        expect(normalizeAnswer(" Caf\u00e9. ")).toBe("cafe");
        expect(normalizeAnswer("Paris")).toBe("paris");
        expect(normalizeAnswer("   ")).toBe("");
    });

    it("matches user answers against any valid correct option", () => {
        expect(isAnswerCorrect("WENT", sampleBlank.options)).toBe(true);
        expect(isAnswerCorrect("  had   gone. ", sampleBlank.options)).toBe(true);
        expect(isAnswerCorrect("goes", sampleBlank.options)).toBe(false);
        expect(isAnswerCorrect("", sampleBlank.options)).toBe(false);
    });

    it("returns all correct option texts in order", () => {
        expect(getCorrectTexts(sampleBlank)).toEqual(["went", "had gone"]);
    });

    it("builds 4 shuffled multiple-choice options with exactly 1 correct and 3 distractors", () => {
        const choices = buildMultipleChoiceOptions(sampleBlank.options);

        expect(choices).toHaveLength(4);
        expect(choices.filter((opt) => opt.is_correct)).toHaveLength(1);
        expect(choices.filter((opt) => !opt.is_correct)).toHaveLength(3);
    });

    it("builds a valid Yes/No candidate from the blank options", () => {
        const candidateTrue = buildYesNoCandidate(sampleBlank.options, () => 0.1);
        expect(candidateTrue?.isCorrect).toBe(true);
        expect(["went", "had gone"]).toContain(candidateTrue?.text);

        const candidateFalse = buildYesNoCandidate(sampleBlank.options, () => 0.9);
        expect(candidateFalse?.isCorrect).toBe(false);
        expect(["go", "goes", "going", "gone"]).toContain(candidateFalse?.text);
    });

    it("parses single-blank, multi-blank, and fallback sentences into ordered segments", () => {
        const single = parseSentenceSegments("She ____ to school.", [sampleBlank]);
        expect(single).toEqual([
            { type: "text", text: "She " },
            { type: "blank", blankIndex: 0, blankPosition: 1 },
            { type: "text", text: " to school." },
        ]);

        const multi = parseSentenceSegments("If I ____ you, I ____ study.", [
            { position: 1, options: [] },
            { position: 2, options: [] },
        ]);
        expect(multi.filter((seg) => seg.type === "blank")).toHaveLength(2);

        const fallback = parseSentenceSegments("Sentence without placeholder", [sampleBlank]);
        expect(fallback.filter((seg) => seg.type === "blank")).toHaveLength(1);
    });

    it("shuffles without mutating the input array", () => {
        const original = [1, 2, 3, 4, 5];
        const copy = [...original];
        const result = shuffle(original);

        expect(original).toEqual(copy);
        expect(result.sort()).toEqual(copy);
    });
});

describe("useQuizSession", () => {
    const sampleQuiz: QuizData = {
        uuid: "quiz-uuid-1",
        title: "Sample Quiz",
        items: [
            {
                position: 1,
                sentence: "She ____ to school.",
                reason: "Past tense is required.",
                blanks: [sampleBlank],
            },
            {
                position: 2,
                sentence: "If I ____ you, I ____ study.",
                reason: "Second conditional uses past subjunctive + would.",
                blanks: [
                    {
                        position: 1,
                        options: [
                            { text: "were", is_correct: true },
                            { text: "am", is_correct: false },
                        ],
                    },
                    {
                        position: 2,
                        options: [
                            { text: "would", is_correct: true },
                            { text: "will", is_correct: false },
                        ],
                    },
                ],
            },
        ],
    };

    it("manages mode selection, multi-blank completion, scoring, tryAgain, and changeMode", () => {
        const session = useQuizSession(sampleQuiz);

        expect(session.phase.value).toBe("mode");
        expect(session.mode.value).toBeNull();

        session.selectMode("open_answer");
        expect(session.phase.value).toBe("question");
        expect(session.mode.value).toBe("open_answer");
        expect(session.currentIndex.value).toBe(0);
        expect(session.isCurrentItemComplete.value).toBe(false);

        // Answer Question 1 correctly using alternative correct option
        session.validateOpenAnswer({ 1: "  HAD GONE. " });
        expect(session.isCurrentItemComplete.value).toBe(true);
        expect(session.isCurrentItemCorrect.value).toBe(true);

        session.nextQuestion();
        expect(session.currentIndex.value).toBe(1);
        expect(session.isLastQuestion.value).toBe(true);

        // Answer Question 2 partially wrong (1 blank right, 1 blank wrong -> item is wrong)
        session.validateOpenAnswer({ 1: "were", 2: "will" });
        expect(session.isCurrentItemComplete.value).toBe(true);
        expect(session.isCurrentItemCorrect.value).toBe(false);

        session.nextQuestion();
        expect(session.phase.value).toBe("results");
        expect(session.score.value).toEqual({
            correctCount: 1,
            totalCount: 2,
            percentage: 50,
        });

        // Try again keeps the mode and restarts at question 1
        session.tryAgain();
        expect(session.phase.value).toBe("question");
        expect(session.mode.value).toBe("open_answer");
        expect(session.currentIndex.value).toBe(0);
        expect(session.isCurrentItemComplete.value).toBe(false);

        // Change mode returns to mode selector
        session.changeMode();
        expect(session.phase.value).toBe("mode");
        expect(session.mode.value).toBeNull();
    });
});
