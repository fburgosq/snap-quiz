export interface QuizSummaryItem {
    uuid: string;
    title: string;
    items_count: number;
    created_at: string | null;
}

export interface PaginationLinkItem {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedQuizzes {
    data: QuizSummaryItem[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLinkItem[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}

export const SNAPQUIZ_UI_STRINGS = {
    brandName: "SnapQuiz",
    nav: {
        ariaLabel: "Main navigation",
        exercises: "Exercises",
        howToAdd: "How to add",
        openMenuAriaLabel: "Open navigation menu",
        closeMenuAriaLabel: "Close navigation menu",
    },
    exercises: {
        pageTitle: "Exercises",
        subtitleCount: (total: number): string =>
            total === 1
                ? "1 saved exercise ready to practice"
                : `${total} saved exercises ready to practice`,
        createExerciseCta: "Create exercise",
        questionCountBadge: (count: number): string =>
            count === 1 ? "1 question" : `${count} questions`,
        startCta: "Start",
        emptyHeading: "No exercises yet",
        emptyDescription:
            "Upload a picture of a fill-in-the-blank worksheet to create your first interactive quiz.",
        emptyActionCta: "Upload an exercise",
        pagination: {
            ariaLabel: "Exercises pagination",
            previous: "Previous",
            next: "Next",
            pageSummary: (current: number, last: number): string => `Page ${current} of ${last}`,
        },
    },
    howToAdd: {
        pageTitle: "How to add an exercise",
        intro: "Upload a picture of a fill-in-the-blank exercise and SnapQuiz turns it into an interactive quiz you can practice right away.",
        stepsHeading: "How it works in 4 simple steps",
        steps: [
            {
                stepNumber: 1,
                title: "Upload an exercise with blanks",
                description:
                    "Upload an image of any exercise that has blanks. The blanks can be empty or already answered.",
                alt: "Step 1 illustration: uploading a worksheet image containing fill-in-the-blank sentences",
                placeholderLabel: "Step 1 — Worksheet with blanks (empty or filled in)",
            },
            {
                stepNumber: 2,
                title: "Use a clear image and title",
                description:
                    "Use a clear image with a clear title that says what the exercise is about or what the student should do.",
                alt: "Step 2 illustration: a clear, well-framed exercise heading and readable sentences",
                placeholderLabel: "Step 2 — Clear heading and sharp, readable text",
            },
            {
                stepNumber: 3,
                title: "Wait a few seconds for analysis",
                description:
                    "Wait a few seconds while the exercise is analyzed. The app reads each sentence, finds or works out the correct answer, creates wrong options and a short explanation.",
                alt: "Step 3 illustration: automatic extraction of sentences, answers, distractors, and explanations",
                placeholderLabel: "Step 3 — Automatic sentence & answer analysis",
            },
            {
                stepNumber: 4,
                title: "Pick a mode and start practicing",
                description:
                    "Open the exercise in the Exercises section and choose how to practice: Multiple choice, Open answer, or Yes / No.",
                alt: "Step 4 illustration: choosing between Multiple choice, Open answer, and Yes / No quiz modes",
                placeholderLabel: "Step 4 — Practice in Multiple choice, Open answer, or Yes / No",
            },
        ],
        tipsHeading: "Tips for a good image",
        tips: [
            "Sharp and well lit",
            "Straight (not tilted)",
            "The whole exercise visible and not cut off",
            "Readable text",
            "A visible title",
            "One exercise per image",
            "Avoid shadows and glare",
        ],
        comparisonHeading: "Good vs. not so good",
        comparisonIntro:
            "A clean photo helps the analyzer read every blank accurately on the first try.",
        goodExample: {
            badge: "Good example",
            title: "Clear, straight, and complete",
            description:
                "Even lighting, flat page, visible title at the top, and every sentence fully inside the frame.",
            alt: "Good example of an exercise photo: well-lit, straight, with a visible title and complete sentences",
            placeholderLabel:
                "Good example placeholder — Sharp, straight photo with full title and sentences",
        },
        badExample: {
            badge: "Not so good",
            title: "Blurry, tilted, or cut off",
            description:
                "Heavy shadows, angled perspective, missing title, or sentences cut off at the edge of the photo.",
            alt: "Not so good example of an exercise photo: tilted, shadowed, with cut-off sentences",
            placeholderLabel:
                "Not so good example placeholder — Tilted, shadowed, or cropped worksheet photo",
        },
        closingHeading: "Ready to try it out?",
        closingSubtext:
            "Take or pick a photo of a fill-in-the-blank worksheet and start practicing in seconds.",
        closingCta: "Add your first exercise",
    },
} as const;

/**
 * Format an ISO-8601 date string into a readable short format (e.g. "Oct 29, 2026").
 */
export function formatShortDate(isoDate: string | null | undefined): string {
    if (!isoDate) {
        return "";
    }

    const parsed = new Date(isoDate);

    if (Number.isNaN(parsed.getTime())) {
        return "";
    }

    return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    }).format(parsed);
}
