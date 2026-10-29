<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import QuizPlayer from "@/components/quiz/QuizPlayer.vue";
import SnapQuizHeader from "@/components/SnapQuizHeader.vue";
import type { QuizData } from "@/lib/quizUtils";

defineProps<{
    quiz: QuizData;
}>();
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <Head :title="quiz.title || 'SnapQuiz'" />

        <SnapQuizHeader />

        <main class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6">
            <QuizPlayer :quiz="quiz" />
        </main>
    </div>
</template>
