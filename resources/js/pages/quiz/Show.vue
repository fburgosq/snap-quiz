<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import QuizPlayer from "@/components/quiz/QuizPlayer.vue";
import type { QuizData } from "@/lib/quizUtils";
import { home } from "@/routes";

defineProps<{
    quiz: QuizData;
}>();
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <Head :title="quiz.title || 'SnapQuiz'" />

        <header class="w-full px-6 py-5 sm:px-10">
            <Link
                :href="home()"
                class="text-xl font-bold tracking-tight text-neutral-900 hover:opacity-80 dark:text-neutral-100"
            >
                SnapQuiz
            </Link>
        </header>

        <main class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6">
            <QuizPlayer :quiz="quiz" />
        </main>
    </div>
</template>
