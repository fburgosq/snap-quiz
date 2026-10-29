<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import SnapQuizHeader from "@/components/SnapQuizHeader.vue";
import { SNAPQUIZ_UI_STRINGS } from "@/lib/navigationStrings";
import { home } from "@/routes";

const rawAssetModules = import.meta.glob<string>("../assets/*.{png,jpg,jpeg,webp,svg,gif,avif}", {
    eager: true,
    import: "default",
    query: "?url",
});

const rootAssetModules = import.meta.glob<string>(
    "../../assets/*.{png,jpg,jpeg,webp,svg,gif,avif}",
    {
        eager: true,
        import: "default",
        query: "?url",
    },
);

interface BundledAsset {
    filename: string;
    url: string;
}

const bundledAssets = computed<BundledAsset[]>(() => {
    const merged = { ...rootAssetModules, ...rawAssetModules };

    return Object.entries(merged)
        .map(([path, url]) => ({
            filename: (path.split("/").pop() ?? path).toLowerCase(),
            url,
        }))
        .sort((a, b) => a.filename.localeCompare(b.filename));
});

const brokenUrls = ref<Record<string, boolean>>({});

function markImageBroken(url: string): void {
    brokenUrls.value = {
        ...brokenUrls.value,
        [url]: true,
    };
}

function isUsableUrl(url: string | null | undefined): url is string {
    return typeof url === "string" && url.length > 0 && !brokenUrls.value[url];
}

const assignedImages = computed(() => {
    const assets = bundledAssets.value;
    const used = new Set<string>();

    const findMatching = (patterns: RegExp[]): string | null => {
        for (const pattern of patterns) {
            const found = assets.find(
                (asset) => !used.has(asset.url) && pattern.test(asset.filename),
            );

            if (found) {
                used.add(found.url);

                return found.url;
            }
        }

        return null;
    };

    const goodUrl = findMatching([/\bgood\b/, /\bcorrect\b/, /\bvalid\b/, /\bok\b/]);
    const badUrl = findMatching([
        /\bbad\b/,
        /\bnot[-_]?so[-_]?good\b/,
        /\bwrong\b/,
        /\bpoor\b/,
        /\bavoid\b/,
    ]);

    const stepUrls: (string | null)[] = [1, 2, 3, 4].map((stepNum) =>
        findMatching([new RegExp(`step[-_ ]?0?${stepNum}\\b`), new RegExp(`^0?${stepNum}[-_.]`)]),
    );

    const remaining = assets.filter((asset) => !used.has(asset.url));
    let remainingIdx = 0;

    for (let i = 0; i < stepUrls.length; i++) {
        if (!stepUrls[i] && remainingIdx < remaining.length) {
            stepUrls[i] = remaining[remainingIdx].url;
            remainingIdx++;
        }
    }

    const finalGood =
        goodUrl ?? (remainingIdx < remaining.length ? remaining[remainingIdx++].url : null);
    const finalBad =
        badUrl ?? (remainingIdx < remaining.length ? remaining[remainingIdx++].url : null);

    return {
        steps: stepUrls,
        good: finalGood,
        bad: finalBad,
    };
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <Head
            :title="`${SNAPQUIZ_UI_STRINGS.howToAdd.pageTitle} - ${SNAPQUIZ_UI_STRINGS.brandName}`"
        />

        <SnapQuizHeader />

        <main class="mx-auto w-full max-w-4xl flex-1 space-y-14 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
            <!-- 1. Intro section -->
            <section class="space-y-3">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ SNAPQUIZ_UI_STRINGS.howToAdd.pageTitle }}
                </h1>
                <p
                    class="max-w-[68ch] text-base leading-relaxed text-neutral-700 dark:text-neutral-300"
                >
                    {{ SNAPQUIZ_UI_STRINGS.howToAdd.intro }}
                </p>
            </section>

            <!-- 2. Numbered steps (single column on mobile; alternating text & image from md) -->
            <section class="space-y-6" aria-labelledby="how-to-add-steps-heading">
                <h2
                    id="how-to-add-steps-heading"
                    class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100"
                >
                    {{ SNAPQUIZ_UI_STRINGS.howToAdd.stepsHeading }}
                </h2>

                <ol class="space-y-8">
                    <li
                        v-for="(step, index) in SNAPQUIZ_UI_STRINGS.howToAdd.steps"
                        :key="step.stepNumber"
                        class="grid grid-cols-1 items-center gap-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs sm:p-6 md:grid-cols-2 md:gap-8 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div
                            class="space-y-3"
                            :class="index % 2 === 1 ? 'md:order-2' : 'md:order-1'"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-sm font-bold text-white dark:bg-neutral-100 dark:text-neutral-900"
                                >
                                    {{ step.stepNumber }}
                                </span>
                                <h3
                                    class="text-lg font-semibold text-neutral-900 dark:text-neutral-100"
                                >
                                    {{ step.title }}
                                </h3>
                            </div>
                            <p
                                class="max-w-[65ch] text-sm leading-relaxed text-neutral-700 sm:text-base dark:text-neutral-300"
                            >
                                {{ step.description }}
                            </p>
                        </div>

                        <div :class="index % 2 === 1 ? 'md:order-1' : 'md:order-2'">
                            <img
                                v-if="isUsableUrl(assignedImages.steps[index])"
                                :src="assignedImages.steps[index]!"
                                :alt="step.alt"
                                width="640"
                                height="400"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                                class="aspect-[16/10] w-full max-w-full rounded-xl border border-neutral-200 object-cover dark:border-neutral-800"
                                @error="markImageBroken(assignedImages.steps[index]!)"
                            />
                            <div
                                v-else
                                role="img"
                                :aria-label="step.alt"
                                class="flex aspect-[16/10] w-full max-w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-neutral-300 bg-neutral-100/80 p-4 text-center dark:border-neutral-700 dark:bg-neutral-800/60"
                            >
                                <span
                                    class="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 shadow-2xs dark:bg-neutral-900 dark:text-neutral-200"
                                >
                                    Illustration Placeholder
                                </span>
                                <p
                                    class="text-xs font-medium text-neutral-600 dark:text-neutral-300"
                                >
                                    {{ step.placeholderLabel }}
                                </p>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- 3. Tips for a good image -->
            <section
                aria-labelledby="how-to-add-tips-heading"
                class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900"
            >
                <h2
                    id="how-to-add-tips-heading"
                    class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100"
                >
                    {{ SNAPQUIZ_UI_STRINGS.howToAdd.tipsHeading }}
                </h2>

                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <li
                        v-for="tip in SNAPQUIZ_UI_STRINGS.howToAdd.tips"
                        :key="tip"
                        class="flex items-start gap-2.5 text-sm text-neutral-700 sm:text-base dark:text-neutral-300"
                    >
                        <span
                            aria-hidden="true"
                            class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                            &#10003;
                        </span>
                        <span>{{ tip }}</span>
                    </li>
                </ul>
            </section>

            <!-- 4. Good vs. not so good comparison -->
            <section aria-labelledby="how-to-add-comparison-heading" class="space-y-5">
                <div class="space-y-1.5">
                    <h2
                        id="how-to-add-comparison-heading"
                        class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100"
                    >
                        {{ SNAPQUIZ_UI_STRINGS.howToAdd.comparisonHeading }}
                    </h2>
                    <p
                        class="max-w-[68ch] text-sm text-neutral-600 sm:text-base dark:text-neutral-400"
                    >
                        {{ SNAPQUIZ_UI_STRINGS.howToAdd.comparisonIntro }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Good example card -->
                    <div
                        class="flex flex-col justify-between gap-4 rounded-2xl border border-emerald-300 bg-emerald-50/50 p-5 dark:border-emerald-900/70 dark:bg-emerald-950/20"
                    >
                        <div class="space-y-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200"
                            >
                                <span aria-hidden="true">&#10003;</span>
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.badge }}
                            </span>
                            <h3
                                class="text-base font-semibold text-neutral-900 dark:text-neutral-100"
                            >
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.title }}
                            </h3>
                            <p
                                class="text-sm leading-relaxed text-neutral-700 dark:text-neutral-300"
                            >
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.description }}
                            </p>
                        </div>

                        <img
                            v-if="isUsableUrl(assignedImages.good)"
                            :src="assignedImages.good!"
                            :alt="SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.alt"
                            width="640"
                            height="400"
                            loading="lazy"
                            class="aspect-[16/10] w-full max-w-full rounded-xl border border-emerald-200 object-cover dark:border-emerald-900"
                            @error="markImageBroken(assignedImages.good!)"
                        />
                        <div
                            v-else
                            role="img"
                            :aria-label="SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.alt"
                            class="flex aspect-[16/10] w-full max-w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-emerald-300 bg-white/80 p-4 text-center dark:border-emerald-800 dark:bg-neutral-900/70"
                        >
                            <span
                                class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                Good Example Placeholder
                            </span>
                            <p class="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.goodExample.placeholderLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Not so good example card -->
                    <div
                        class="flex flex-col justify-between gap-4 rounded-2xl border border-rose-300 bg-rose-50/50 p-5 dark:border-rose-900/70 dark:bg-rose-950/20"
                    >
                        <div class="space-y-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-900 dark:bg-rose-900/60 dark:text-rose-200"
                            >
                                <span aria-hidden="true">&#10007;</span>
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.badExample.badge }}
                            </span>
                            <h3
                                class="text-base font-semibold text-neutral-900 dark:text-neutral-100"
                            >
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.badExample.title }}
                            </h3>
                            <p
                                class="text-sm leading-relaxed text-neutral-700 dark:text-neutral-300"
                            >
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.badExample.description }}
                            </p>
                        </div>

                        <img
                            v-if="isUsableUrl(assignedImages.bad)"
                            :src="assignedImages.bad!"
                            :alt="SNAPQUIZ_UI_STRINGS.howToAdd.badExample.alt"
                            width="640"
                            height="400"
                            loading="lazy"
                            class="aspect-[16/10] w-full max-w-full rounded-xl border border-rose-200 object-cover dark:border-rose-900"
                            @error="markImageBroken(assignedImages.bad!)"
                        />
                        <div
                            v-else
                            role="img"
                            :aria-label="SNAPQUIZ_UI_STRINGS.howToAdd.badExample.alt"
                            class="flex aspect-[16/10] w-full max-w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-rose-300 bg-white/80 p-4 text-center dark:border-rose-800 dark:bg-neutral-900/70"
                        >
                            <span
                                class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-900 dark:bg-rose-950 dark:text-rose-300"
                            >
                                Not So Good Placeholder
                            </span>
                            <p class="text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                {{ SNAPQUIZ_UI_STRINGS.howToAdd.badExample.placeholderLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Closing CTA -->
            <section
                class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-neutral-200 bg-white p-6 text-center shadow-xs sm:flex-row sm:text-left dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="space-y-1">
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                        {{ SNAPQUIZ_UI_STRINGS.howToAdd.closingHeading }}
                    </h2>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                        {{ SNAPQUIZ_UI_STRINGS.howToAdd.closingSubtext }}
                    </p>
                </div>

                <Link
                    :href="home.url()"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-neutral-800 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200 dark:focus-visible:ring-neutral-100"
                >
                    {{ SNAPQUIZ_UI_STRINGS.howToAdd.closingCta }}
                </Link>
            </section>
        </main>
    </div>
</template>
