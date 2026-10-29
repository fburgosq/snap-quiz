<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { QUIZ_STRINGS } from "@/lib/quizUtils";

const props = withDefaults(
    defineProps<{
        open: boolean;
        src: string | null;
        title: string;
        triggerElement?: HTMLElement | null;
    }>(),
    {
        triggerElement: null,
    },
);

const emit = defineEmits<{
    close: [];
}>();

const dialogRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);
const isLoading = ref<boolean>(false);
const hasError = ref<boolean>(false);
const previousFocusedElement = ref<HTMLElement | null>(null);
let savedBodyOverflow: string | null = null;

const ariaLabelText = computed<string>(() =>
    QUIZ_STRINGS.modalAriaLabel(props.title || "Exercise"),
);

const imageAltText = computed<string>(() => QUIZ_STRINGS.modalImageAlt(props.title || "Exercise"));

function lockBodyScroll(): void {
    if (typeof document === "undefined") {
        return;
    }

    if (savedBodyOverflow === null) {
        savedBodyOverflow = document.body.style.overflow;
    }

    document.body.style.overflow = "hidden";
}

function unlockBodyScroll(): void {
    if (typeof document === "undefined") {
        return;
    }

    if (savedBodyOverflow !== null) {
        document.body.style.overflow = savedBodyOverflow;
        savedBodyOverflow = null;
    }
}

function handleClose(): void {
    if (!props.open) {
        return;
    }

    emit("close");
}

function handleImageLoad(): void {
    isLoading.value = false;
    hasError.value = false;
}

function handleImageError(): void {
    isLoading.value = false;
    hasError.value = true;
}

function getFocusableElements(): HTMLElement[] {
    if (!dialogRef.value) {
        return [];
    }

    const selector = [
        "button:not([disabled])",
        "[href]",
        "input:not([disabled])",
        "select:not([disabled])",
        "textarea:not([disabled])",
        '[tabindex]:not([tabindex="-1"])',
    ].join(", ");

    return Array.from(dialogRef.value.querySelectorAll<HTMLElement>(selector));
}

function handleKeydown(event: KeyboardEvent): void {
    if (!props.open) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        handleClose();

        return;
    }

    if (event.key === "Tab") {
        const focusable = getFocusableElements();

        if (focusable.length === 0) {
            event.preventDefault();

            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = typeof document !== "undefined" ? document.activeElement : null;

        if (event.shiftKey) {
            if (active === first || !dialogRef.value?.contains(active)) {
                event.preventDefault();
                last.focus();
            }
        } else if (active === last || !dialogRef.value?.contains(active)) {
            event.preventDefault();
            first.focus();
        }
    }
}

watch(
    () => [props.open, props.src] as const,
    async ([isOpen, currentSrc], oldVal) => {
        const wasOpen = oldVal?.[0] ?? false;

        if (isOpen && !wasOpen) {
            if (typeof document !== "undefined") {
                previousFocusedElement.value =
                    props.triggerElement ??
                    (document.activeElement instanceof HTMLElement ? document.activeElement : null);
            }

            lockBodyScroll();
            hasError.value = !currentSrc;
            isLoading.value = Boolean(currentSrc);

            await nextTick();
            closeButtonRef.value?.focus();
        } else if (isOpen && wasOpen) {
            hasError.value = !currentSrc;
            isLoading.value = Boolean(currentSrc);
        } else if (!isOpen && wasOpen) {
            unlockBodyScroll();
            isLoading.value = false;
            hasError.value = false;

            const toRestore = props.triggerElement ?? previousFocusedElement.value;
            previousFocusedElement.value = null;

            await nextTick();
            toRestore?.focus?.();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    unlockBodyScroll();
});
</script>

<template>
    <div>
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-3 backdrop-blur-xs transition-opacity duration-150 motion-reduce:transition-none sm:p-6"
            @click.self="handleClose"
            @keydown="handleKeydown"
        >
            <div
                ref="dialogRef"
                role="dialog"
                aria-modal="true"
                :aria-label="ariaLabelText"
                aria-labelledby="snapquiz-image-modal-title"
                class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl transition-transform duration-150 motion-reduce:transition-none dark:border-neutral-800 dark:bg-neutral-900"
                @click.stop
            >
                <!-- Modal header -->
                <div
                    class="flex items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3 sm:px-6 dark:border-neutral-800"
                >
                    <h2
                        id="snapquiz-image-modal-title"
                        :title="title"
                        class="truncate text-sm font-semibold text-neutral-900 sm:text-base dark:text-neutral-100"
                    >
                        {{ ariaLabelText }}
                    </h2>

                    <button
                        ref="closeButtonRef"
                        type="button"
                        :aria-label="QUIZ_STRINGS.modalCloseAriaLabel"
                        class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-700 transition hover:bg-neutral-100 hover:text-neutral-900 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:focus-visible:ring-neutral-100"
                        @click="handleClose"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Scrollable image body (preserves native touch-action for mobile pinch-zoom) -->
                <div
                    class="relative flex min-h-56 flex-1 items-center justify-center overflow-auto bg-neutral-100/70 p-3 sm:p-6 dark:bg-neutral-950/70"
                >
                    <!-- Loading skeleton / spinner -->
                    <div
                        v-if="isLoading && !hasError"
                        role="status"
                        aria-live="polite"
                        class="flex flex-col items-center justify-center gap-3 py-12 text-center"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-2 border-neutral-300 border-t-neutral-900 motion-reduce:animate-none dark:border-neutral-700 dark:border-t-neutral-100"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-400">
                            {{ QUIZ_STRINGS.modalLoadingLabel }}
                        </p>
                    </div>

                    <!-- Error state -->
                    <div
                        v-if="hasError"
                        role="alert"
                        class="flex flex-col items-center justify-center gap-3 px-4 py-12 text-center"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300"
                            aria-hidden="true"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"
                                />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">
                            {{ QUIZ_STRINGS.modalErrorMessage }}
                        </p>
                    </div>

                    <!-- Image rendered ONLY when modal is open -->
                    <img
                        v-if="open && src && !hasError"
                        :src="src"
                        :alt="imageAltText"
                        class="max-h-[80vh] max-w-full rounded-lg object-contain transition-opacity duration-150 motion-reduce:transition-none"
                        :class="isLoading ? 'opacity-0' : 'opacity-100'"
                        @load="handleImageLoad"
                        @error="handleImageError"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
