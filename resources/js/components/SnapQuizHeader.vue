<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useCurrentUrl } from "@/composables/useCurrentUrl";
import { SNAPQUIZ_UI_STRINGS } from "@/lib/navigationStrings";
import { home, howToAdd } from "@/routes";
import { index as exercisesIndex } from "@/routes/exercises";

const { isCurrentUrl } = useCurrentUrl();

const isMobileMenuOpen = ref<boolean>(false);
const mobileMenuId = "snapquiz-mobile-nav-menu";

const navItems = computed(() => [
    {
        label: SNAPQUIZ_UI_STRINGS.nav.exercises,
        href: exercisesIndex.url(),
        active: isCurrentUrl(exercisesIndex.url(), undefined, true),
    },
    {
        label: SNAPQUIZ_UI_STRINGS.nav.howToAdd,
        href: howToAdd.url(),
        active: isCurrentUrl(howToAdd.url(), undefined, true),
    },
]);

function toggleMobileMenu(): void {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

function closeMobileMenu(): void {
    isMobileMenuOpen.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape" && isMobileMenuOpen.value) {
        closeMobileMenu();
    }
}

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
    <header
        class="w-full border-b border-neutral-200/80 bg-white/90 backdrop-blur-xs dark:border-neutral-800 dark:bg-neutral-950/90"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 md:py-4 lg:px-10"
        >
            <Link
                :href="home.url()"
                class="inline-flex min-h-11 items-center text-xl font-bold tracking-tight text-neutral-900 transition hover:opacity-80 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none rounded-md px-1 dark:text-neutral-100 dark:focus-visible:ring-neutral-100"
                @click="closeMobileMenu"
            >
                {{ SNAPQUIZ_UI_STRINGS.brandName }}
            </Link>

            <nav :aria-label="SNAPQUIZ_UI_STRINGS.nav.ariaLabel" class="flex items-center">
                <!-- Desktop inline navigation (md and up) -->
                <ul class="hidden md:flex md:items-center md:gap-2">
                    <li v-for="item in navItems" :key="item.href">
                        <Link
                            :href="item.href"
                            :aria-current="item.active ? 'page' : undefined"
                            class="inline-flex min-h-10 items-center rounded-lg px-3.5 py-2 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-neutral-100"
                            :class="
                                item.active
                                    ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                    : 'text-neutral-700 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-neutral-100'
                            "
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>

                <!-- Mobile hamburger toggle button (below md) -->
                <button
                    type="button"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-neutral-200 p-2 text-neutral-700 transition hover:bg-neutral-100 hover:text-neutral-900 focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none md:hidden dark:border-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-900 dark:focus-visible:ring-neutral-100"
                    :aria-expanded="isMobileMenuOpen ? 'true' : 'false'"
                    :aria-controls="mobileMenuId"
                    :aria-label="
                        isMobileMenuOpen
                            ? SNAPQUIZ_UI_STRINGS.nav.closeMenuAriaLabel
                            : SNAPQUIZ_UI_STRINGS.nav.openMenuAriaLabel
                    "
                    @click="toggleMobileMenu"
                >
                    <svg
                        v-if="!isMobileMenuOpen"
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
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                    <svg
                        v-else
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
            </nav>
        </div>

        <!-- Mobile collapsible navigation panel (below md) -->
        <div
            :id="mobileMenuId"
            v-show="isMobileMenuOpen"
            class="border-t border-neutral-200 bg-white px-4 py-2 md:hidden dark:border-neutral-800 dark:bg-neutral-950"
        >
            <ul class="flex flex-col gap-1">
                <li v-for="item in navItems" :key="`mobile-${item.href}`">
                    <Link
                        :href="item.href"
                        :aria-current="item.active ? 'page' : undefined"
                        class="flex min-h-11 w-full items-center rounded-lg px-3.5 py-2.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-neutral-100"
                        :class="
                            item.active
                                ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                : 'text-neutral-700 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-900 dark:hover:text-neutral-100'
                        "
                        @click="closeMobileMenu"
                    >
                        {{ item.label }}
                    </Link>
                </li>
            </ul>
        </div>
    </header>
</template>
