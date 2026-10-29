<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ImageModal from "@/components/quiz/ImageModal.vue";
import SnapQuizHeader from "@/components/SnapQuizHeader.vue";
import {
    formatShortDate,
    SNAPQUIZ_UI_STRINGS,
    type PaginatedQuizzes,
    type PaginationLinkItem,
    type QuizSummaryItem,
} from "@/lib/navigationStrings";
import { home } from "@/routes";
import { show as showQuiz } from "@/routes/quiz";

const props = defineProps<{
    quizzes: PaginatedQuizzes;
}>();

const items = computed(() => props.quizzes?.data ?? []);
const totalCount = computed(() => props.quizzes?.meta?.total ?? items.value.length);
const currentPage = computed(() => props.quizzes?.meta?.current_page ?? 1);
const lastPage = computed(() => props.quizzes?.meta?.last_page ?? 1);
const prevPageUrl = computed(() => props.quizzes?.links?.prev ?? null);
const nextPageUrl = computed(() => props.quizzes?.links?.next ?? null);

const numberedLinks = computed<PaginationLinkItem[]>(() => {
    const rawLinks = props.quizzes?.meta?.links ?? [];

    if (rawLinks.length <= 2) {
        return [];
    }

    return rawLinks.slice(1, -1);
});

const modalOpen = ref<boolean>(false);
const modalImageSrc = ref<string | null>(null);
const modalQuizTitle = ref<string>("");
const modalTriggerElement = ref<HTMLElement | null>(null);

function openOriginalImageModal(quiz: QuizSummaryItem, event: MouseEvent): void {
    if (!quiz.has_image || !quiz.image_url) {
        return;
    }

    modalTriggerElement.value =
        event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    modalImageSrc.value = quiz.image_url;
    modalQuizTitle.value = quiz.title;
    modalOpen.value = true;
}

function closeOriginalImageModal(): void {
    modalOpen.value = false;
}
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <Head
            :title="`${SNAPQUIZ_UI_STRINGS.exercises.pageTitle} - ${SNAPQUIZ_UI_STRINGS.brandName}`"
        />

        <SnapQuizHeader />

        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-8 sm:px-6 lg:px-10">
            <!-- Page header -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ SNAPQUIZ_UI_STRINGS.exercises.pageTitle }}
                    </h1>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                        {{ SNAPQUIZ_UI_STRINGS.exercises.subtitleCount(totalCount) }}
                    </p>
                </div>

                <Link
                    :href="home.url()"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-neutral-800 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200 dark:focus-visible:ring-neutral-100"
                >
                    {{ SNAPQUIZ_UI_STRINGS.exercises.createExerciseCta }}
                </Link>
            </div>

            <!-- Empty state -->
            <section
                v-if="items.length === 0"
                class="flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div
                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                    aria-hidden="true"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.75"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ SNAPQUIZ_UI_STRINGS.exercises.emptyHeading }}
                </h2>
                <p class="mt-1.5 max-w-md text-sm text-neutral-600 dark:text-neutral-400">
                    {{ SNAPQUIZ_UI_STRINGS.exercises.emptyDescription }}
                </p>
                <Link
                    :href="home.url()"
                    class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-neutral-800 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200 dark:focus-visible:ring-neutral-100"
                >
                    {{ SNAPQUIZ_UI_STRINGS.exercises.emptyActionCta }}
                </Link>
            </section>

            <!-- Exercises grid (1 / 2 / 3 / 4 / 6 columns) -->
            <section v-else class="flex flex-1 flex-col justify-between gap-8">
                <ul
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                >
                    <li v-for="quiz in items" :key="quiz.uuid" class="flex">
                        <article
                            class="group/card relative flex h-full w-full flex-col justify-between rounded-xl border border-neutral-200 bg-white p-4 shadow-xs transition hover:-translate-y-0.5 hover:border-neutral-400 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-600"
                        >
                            <!-- Top-right "View Original Image" button (above stretched link overlay) -->
                            <div
                                v-if="quiz.has_image && quiz.image_url"
                                class="group/tooltip absolute top-2 right-2 z-20"
                            >
                                <button
                                    type="button"
                                    :aria-label="
                                        SNAPQUIZ_UI_STRINGS.exercises.viewOriginalImageAriaLabel
                                    "
                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100 dark:focus-visible:ring-neutral-100"
                                    @click.stop.prevent="openOriginalImageModal(quiz, $event)"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-4.5 w-4.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M21 15l-5-5L5 21"
                                        />
                                    </svg>
                                </button>

                                <!-- Hover & keyboard focus tooltip -->
                                <span
                                    role="tooltip"
                                    class="pointer-events-none invisible absolute -top-9 right-0 z-30 rounded-md bg-neutral-900 px-2.5 py-1 text-xs font-medium whitespace-nowrap text-white opacity-0 shadow-md transition-opacity group-focus-within/tooltip:visible group-focus-within/tooltip:opacity-100 group-hover/tooltip:visible group-hover/tooltip:opacity-100 dark:bg-neutral-100 dark:text-neutral-900"
                                >
                                    {{ SNAPQUIZ_UI_STRINGS.exercises.viewOriginalImageTooltip }}
                                </span>
                            </div>

                            <div
                                class="space-y-3"
                                :class="quiz.has_image && quiz.image_url ? 'pr-11' : ''"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                                    >
                                        {{
                                            SNAPQUIZ_UI_STRINGS.exercises.questionCountBadge(
                                                quiz.items_count,
                                            )
                                        }}
                                    </span>

                                    <time
                                        v-if="quiz.created_at"
                                        :datetime="quiz.created_at"
                                        class="text-xs text-neutral-500 dark:text-neutral-400"
                                    >
                                        {{ formatShortDate(quiz.created_at) }}
                                    </time>
                                </div>

                                <h2
                                    :title="quiz.title"
                                    class="line-clamp-2 break-words text-base font-semibold leading-snug text-neutral-900 group-hover/card:text-neutral-700 dark:text-neutral-100 dark:group-hover/card:text-neutral-300"
                                >
                                    {{ quiz.title }}
                                </h2>
                            </div>

                            <!-- Stretched link covering the card without nesting the icon button -->
                            <Link
                                :href="showQuiz.url(quiz.uuid)"
                                :aria-label="`${SNAPQUIZ_UI_STRINGS.exercises.startCta}: ${quiz.title}`"
                                class="mt-5 flex items-center justify-between border-t border-neutral-100 pt-3 text-sm font-semibold text-neutral-900 after:absolute after:inset-0 after:z-10 after:rounded-xl after:content-[''] focus-visible:outline-none after:focus-visible:ring-2 after:focus-visible:ring-neutral-900 after:focus-visible:ring-offset-2 dark:border-neutral-800 dark:text-neutral-100 dark:after:focus-visible:ring-neutral-100"
                            >
                                <span>{{ SNAPQUIZ_UI_STRINGS.exercises.startCta }}</span>
                                <span
                                    aria-hidden="true"
                                    class="transition-transform group-hover/card:translate-x-0.5"
                                >
                                    &rarr;
                                </span>
                            </Link>
                        </article>
                    </li>
                </ul>

                <!-- Pagination controls -->
                <nav
                    v-if="lastPage > 1"
                    :aria-label="SNAPQUIZ_UI_STRINGS.exercises.pagination.ariaLabel"
                    class="flex flex-col items-center justify-between gap-4 border-t border-neutral-200 pt-6 sm:flex-row dark:border-neutral-800"
                >
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                        {{
                            SNAPQUIZ_UI_STRINGS.exercises.pagination.pageSummary(
                                currentPage,
                                lastPage,
                            )
                        }}
                    </p>

                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                        <Link
                            v-if="prevPageUrl"
                            :href="prevPageUrl"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:focus-visible:ring-neutral-100"
                        >
                            {{ SNAPQUIZ_UI_STRINGS.exercises.pagination.previous }}
                        </Link>
                        <span
                            v-else
                            aria-disabled="true"
                            class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-lg border border-neutral-200 bg-neutral-100 px-3.5 py-2 text-sm font-medium text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-600"
                        >
                            {{ SNAPQUIZ_UI_STRINGS.exercises.pagination.previous }}
                        </span>

                        <!-- Desktop page numbers -->
                        <div class="hidden sm:flex sm:items-center sm:gap-1">
                            <template
                                v-for="(pageLink, index) in numberedLinks"
                                :key="`page-${index}-${pageLink.label}`"
                            >
                                <Link
                                    v-if="pageLink.url"
                                    :href="pageLink.url"
                                    :aria-current="pageLink.active ? 'page' : undefined"
                                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-neutral-100"
                                    :class="
                                        pageLink.active
                                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                            : 'border border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800'
                                    "
                                >
                                    {{ pageLink.label }}
                                </Link>
                                <span
                                    v-else
                                    class="inline-flex min-h-10 min-w-10 items-center justify-center px-2 text-sm text-neutral-400"
                                >
                                    {{ pageLink.label }}
                                </span>
                            </template>
                        </div>

                        <Link
                            v-if="nextPageUrl"
                            :href="nextPageUrl"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:focus-visible:ring-neutral-100"
                        >
                            {{ SNAPQUIZ_UI_STRINGS.exercises.pagination.next }}
                        </Link>
                        <span
                            v-else
                            aria-disabled="true"
                            class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-lg border border-neutral-200 bg-neutral-100 px-3.5 py-2 text-sm font-medium text-neutral-400 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-600"
                        >
                            {{ SNAPQUIZ_UI_STRINGS.exercises.pagination.next }}
                        </span>
                    </div>
                </nav>
            </section>
        </main>

        <!-- Single page-level ImageModal instance -->
        <ImageModal
            :open="modalOpen"
            :src="modalImageSrc"
            :title="modalQuizTitle"
            :trigger-element="modalTriggerElement"
            @close="closeOriginalImageModal"
        />
    </div>
</template>
