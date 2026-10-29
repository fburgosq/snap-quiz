<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import { onBeforeUnmount, ref } from "vue";
import InputError from "@/components/InputError.vue";
import SnapQuizHeader from "@/components/SnapQuizHeader.vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { store } from "@/routes/quiz";

interface QuizExercise {
    sentence: string;
    correct: string | string[];
    incorrect: string[];
    reason: string;
}

interface QuizResult {
    title: string;
    exercise: QuizExercise[];
}

defineProps<{
    quiz?: QuizResult | null;
    quizUuid?: string | null;
}>();

const form = useForm<{
    image: File | null;
}>({
    image: null,
});

const selectedFileName = ref<string>("");
const previewUrl = ref<string | null>(null);

function revokePreview(): void {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    revokePreview();
    form.image = file;
    form.clearErrors("image");

    if (file !== null) {
        selectedFileName.value = file.name;

        if (file.type.startsWith("image/")) {
            previewUrl.value = URL.createObjectURL(file);
        }
    } else {
        selectedFileName.value = "";
    }
}

function submit(): void {
    form.post(store.url(), {
        forceFormData: true,
        preserveScroll: true,
    });
}

onBeforeUnmount(() => {
    revokePreview();
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <Head title="SnapQuiz" />

        <SnapQuizHeader />

        <main class="flex flex-1 items-center justify-center px-6 py-12">
            <div class="w-full max-w-md space-y-6">
                <form
                    class="space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900"
                    @submit.prevent="submit"
                >
                    <div class="space-y-2">
                        <Label for="image" class="text-sm font-medium"> Upload Image </Label>

                        <input
                            id="image"
                            name="image"
                            type="file"
                            accept="image/*"
                            class="block w-full cursor-pointer rounded-md border border-neutral-300 bg-transparent px-3 py-2 text-sm text-neutral-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-neutral-800 hover:file:bg-neutral-200 focus:outline-none dark:border-neutral-700 dark:text-neutral-200 dark:file:bg-neutral-800 dark:file:text-neutral-200"
                            @change="handleFileChange"
                        />

                        <div
                            v-if="selectedFileName"
                            class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-950"
                        >
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                :alt="selectedFileName"
                                class="h-12 w-12 shrink-0 rounded object-cover"
                            />
                            <p class="truncate text-xs text-neutral-600 dark:text-neutral-400">
                                {{ selectedFileName }}
                            </p>
                        </div>

                        <InputError :message="form.errors.image" />
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing">
                        {{ form.processing ? "Creating Quiz..." : "Create Quiz" }}
                    </Button>
                </form>

                <section
                    v-if="quiz"
                    class="space-y-3 rounded-xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <h2 class="text-base font-semibold">
                        {{ quiz.title }}
                    </h2>
                    <ol class="list-decimal space-y-2 pl-5 text-sm">
                        <li
                            v-for="(item, index) in quiz.exercise"
                            :key="index"
                            class="text-neutral-700 dark:text-neutral-300"
                        >
                            {{ item.sentence }}
                        </li>
                    </ol>

                    <div v-if="quizUuid" class="pt-2">
                        <Link
                            :href="`/quiz/${quizUuid}`"
                            class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-neutral-800 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-200"
                        >
                            Play Quiz
                        </Link>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
