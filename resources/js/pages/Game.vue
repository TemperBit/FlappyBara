<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUp,
    Gauge,
    Pause,
    Play,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    TimerReset,
    Volume2,
    VolumeX,
} from '@lucide/vue';
import { computed } from 'vue';
import { useFlappyBaraGame } from '@/composables/useFlappyBaraGame';
import { dashboard, home, login, register } from '@/routes';

const {
    activeEffects,
    canPause,
    canvasRef,
    flap,
    highScore,
    isMuted,
    lastPickup,
    phase,
    score,
    startGame,
    statusLabel,
    toggleMuted,
    togglePause,
} = useFlappyBaraGame();

const overlayTitle = computed(() => {
    if (phase.value === 'paused') {
        return 'Catch your breath';
    }

    if (phase.value === 'game-over') {
        return score.value === highScore.value && score.value > 0
            ? 'A new best run'
            : 'Bara took a dip';
    }

    return 'Ready for the river?';
});

const overlayDescription = computed(() => {
    if (phase.value === 'paused') {
        return 'The river is holding still. Resume when you are ready.';
    }

    if (phase.value === 'game-over') {
        return `You cleared ${score.value} ${score.value === 1 ? 'gate' : 'gates'} this run.`;
    }

    return 'Tap, click, or press Space to keep Bara in the breeze.';
});

const primaryActionLabel = computed(() => {
    if (phase.value === 'paused') {
        return 'Resume run';
    }

    if (phase.value === 'game-over') {
        return 'Try again';
    }

    return 'Start run';
});

const pickupAnnouncement = computed(() => {
    if (lastPickup.value === 'shield') {
        return 'Bubble shield collected.';
    }

    if (lastPickup.value === 'slow-time') {
        return 'River time collected. Obstacles are moving more slowly.';
    }

    if (lastPickup.value === 'double-score') {
        return 'Golden guava collected. Scores are doubled.';
    }

    return '';
});

function runPrimaryAction(): void {
    if (phase.value === 'paused') {
        togglePause();

        return;
    }

    startGame();
}

function handleCanvasPointer(event: PointerEvent): void {
    event.preventDefault();
    canvasRef.value?.focus();
    flap();
}
</script>

<template>
    <Head title="Play">
        <meta
            head-key="description"
            name="description"
            content="Play FlappyBara, a cozy arcade game with clever power-ups."
        />
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div
        class="isolate min-h-dvh overflow-x-hidden bg-[#f7f1df] font-game text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div
            class="pointer-events-none fixed inset-0 overflow-hidden"
            aria-hidden="true"
        >
            <div
                class="absolute -top-28 left-[8%] size-80 rounded-full bg-[#f4c967]/25 blur-3xl dark:bg-[#f4c967]/10"
            />
            <div
                class="absolute top-[42%] -right-36 size-96 rounded-full bg-[#78bfa3]/25 blur-3xl dark:bg-[#78bfa3]/10"
            />
        </div>

        <header class="relative">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8"
            >
                <Link
                    :href="home()"
                    aria-label="Homepage"
                    class="group inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600"
                >
                    <span
                        class="relative size-10 shrink-0 rounded-[44%_44%_48%_48%] bg-[#a87550] ring-1 ring-[#674534]/20 dark:ring-white/10"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute top-0.5 left-1 size-3 rounded-full bg-[#855a42]"
                        />
                        <span
                            class="absolute top-0.5 right-1 size-3 rounded-full bg-[#855a42]"
                        />
                        <span
                            class="absolute top-3.5 right-2.5 size-1.5 rounded-full bg-[#342720]"
                        />
                        <span
                            class="absolute right-1.5 bottom-2.5 h-2 w-3 rounded-full bg-[#4f352c]"
                        />
                    </span>
                    <span class="min-w-0">
                        <span class="font-semibold tracking-tight"
                            >FlappyBara</span
                        >
                        <span
                            class="hidden text-neutral-600 sm:inline dark:text-neutral-400"
                        >
                            / Meadow run
                        </span>
                    </span>
                </Link>

                <nav
                    class="flex items-center gap-1 sm:gap-2"
                    aria-label="Account"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="relative rounded-lg px-3 py-2 font-medium text-neutral-700 ring-1 ring-neutral-950/10 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-200 dark:ring-white/10 dark:hover:bg-white/5"
                    >
                        Dashboard
                        <span
                            class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                            aria-hidden="true"
                        />
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="relative rounded-lg px-3 py-2 font-medium text-neutral-700 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-200 dark:hover:bg-white/5"
                        >
                            Log in
                            <span
                                class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            :href="register()"
                            class="relative rounded-lg px-3 py-2 font-medium text-neutral-800 ring-1 ring-neutral-950/10 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-100 dark:ring-white/15 dark:hover:bg-white/5"
                        >
                            Create account
                            <span
                                class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                                aria-hidden="true"
                            />
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main
            class="relative mx-auto grid max-w-7xl items-start gap-10 px-4 py-8 sm:px-6 sm:py-12 lg:grid-cols-[7fr_11fr] lg:grid-rows-[auto_1fr] lg:gap-x-14 lg:gap-y-10 lg:px-8 lg:py-16"
        >
            <section
                class="flex min-w-0 flex-col gap-8 lg:col-start-1 lg:row-start-1"
            >
                <div class="flex flex-col gap-5">
                    <div
                        class="flex w-fit items-center gap-2 rounded-full bg-[#e7dcc0] py-1.5 pr-3 pl-1.5 text-neutral-700 ring-1 ring-neutral-950/5 sm:text-sm dark:bg-white/8 dark:text-neutral-200 dark:ring-white/5"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full bg-emerald-600"
                            aria-hidden="true"
                        />
                        Single-player alpha
                    </div>

                    <div class="flex flex-col gap-4">
                        <h1
                            class="max-w-[18ch] text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl"
                        >
                            Little paws. Big air.
                        </h1>
                        <p
                            class="max-w-[48ch] text-lg text-pretty text-neutral-600 sm:text-base dark:text-neutral-300"
                        >
                            Guide Bara through the wetlands, collect clever
                            power-ups, and chase a longer run every time.
                        </p>
                    </div>
                </div>

                <dl
                    class="grid grid-cols-2 divide-x divide-neutral-950/10 dark:divide-white/10"
                >
                    <div class="flex flex-col gap-1 pr-6">
                        <dt
                            class="text-neutral-600 sm:text-sm dark:text-neutral-400"
                        >
                            Current score
                        </dt>
                        <dd
                            class="text-3xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ score }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 pl-6">
                        <dt
                            class="text-neutral-600 sm:text-sm dark:text-neutral-400"
                        >
                            Personal best
                        </dt>
                        <dd
                            class="text-3xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ highScore }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1"
                aria-label="FlappyBara game"
            >
                <div
                    class="rounded-(--game-radius) bg-white/85 p-(--game-padding) shadow-xl ring-1 ring-neutral-950/10 backdrop-blur-sm [--game-padding:--spacing(2)] [--game-radius:min(3vw,1.75rem)] dark:bg-white/6 dark:shadow-none dark:ring-white/10"
                >
                    <div
                        class="flex items-center justify-between gap-3 px-2 py-2 sm:px-3"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :class="
                                    phase === 'playing'
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                                aria-hidden="true"
                            />
                            <p class="truncate font-medium sm:text-sm">
                                {{ statusLabel }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                class="relative inline-flex size-9 items-center justify-center rounded-lg text-neutral-600 ring-1 ring-transparent hover:bg-neutral-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:text-neutral-300 dark:hover:bg-white/8"
                                :aria-label="
                                    isMuted ? 'Turn sound on' : 'Mute sound'
                                "
                                :title="
                                    isMuted ? 'Turn sound on' : 'Mute sound'
                                "
                                @click="toggleMuted"
                            >
                                <VolumeX
                                    v-if="isMuted"
                                    class="size-4 shrink-0 stroke-current"
                                />
                                <Volume2
                                    v-else
                                    class="size-4 shrink-0 stroke-current"
                                />
                                <span
                                    class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                                    aria-hidden="true"
                                />
                            </button>
                            <button
                                type="button"
                                class="relative inline-flex size-9 items-center justify-center rounded-lg text-neutral-600 ring-1 ring-transparent hover:bg-neutral-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-neutral-300 dark:hover:bg-white/8"
                                :disabled="!canPause"
                                :aria-label="
                                    phase === 'paused'
                                        ? 'Resume game'
                                        : 'Pause game'
                                "
                                :title="
                                    phase === 'paused'
                                        ? 'Resume game'
                                        : 'Pause game'
                                "
                                @click="togglePause"
                            >
                                <Play
                                    v-if="phase === 'paused'"
                                    class="size-4 shrink-0 fill-current stroke-current"
                                />
                                <Pause
                                    v-else
                                    class="size-4 shrink-0 stroke-current"
                                />
                                <span
                                    class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-[calc(var(--game-radius)-var(--game-padding))] bg-[#d8f2ec] ring-1 ring-neutral-950/10 dark:bg-[#152f3c] dark:ring-white/10"
                    >
                        <canvas
                            ref="canvasRef"
                            class="aspect-4/5 w-full cursor-pointer touch-none outline-none focus-visible:ring-4 focus-visible:ring-amber-300 focus-visible:ring-inset"
                            tabindex="0"
                            role="application"
                            aria-label="FlappyBara. Tap the game or press Space or the Up Arrow to flap. Press P to pause and M to mute."
                            @pointerdown="handleCanvasPointer"
                        />

                        <div
                            class="pointer-events-none absolute top-3 right-3 left-3 flex items-start justify-between gap-3 sm:top-4 sm:right-4 sm:left-4"
                        >
                            <div
                                class="rounded-xl bg-neutral-950/65 px-3 py-2 text-white shadow-lg ring-1 ring-white/15 backdrop-blur-md dark:shadow-none"
                            >
                                <p
                                    class="text-[0.6875rem] font-medium tracking-wide text-white/70 uppercase"
                                >
                                    Score
                                </p>
                                <p
                                    class="text-2xl font-semibold tracking-tight tabular-nums"
                                >
                                    {{ score }}
                                </p>
                            </div>

                            <div
                                class="flex max-w-[60%] flex-col items-end gap-2"
                            >
                                <div
                                    v-for="effect in activeEffects"
                                    :key="effect.kind"
                                    class="flex items-center gap-2 rounded-full bg-white/88 py-1.5 pr-3 pl-1.5 text-neutral-800 shadow-lg ring-1 ring-neutral-950/10 backdrop-blur-md dark:bg-neutral-950/75 dark:text-white dark:shadow-none dark:ring-white/15"
                                >
                                    <ShieldCheck
                                        v-if="effect.kind === 'shield'"
                                        class="size-4 shrink-0 stroke-emerald-600"
                                        aria-hidden="true"
                                    />
                                    <TimerReset
                                        v-else-if="effect.kind === 'slow-time'"
                                        class="size-4 shrink-0 stroke-sky-600"
                                        aria-hidden="true"
                                    />
                                    <Sparkles
                                        v-else
                                        class="size-4 shrink-0 stroke-amber-600"
                                        aria-hidden="true"
                                    />
                                    <p
                                        class="truncate font-medium tabular-nums sm:text-sm"
                                    >
                                        {{ effect.label }} ·
                                        {{ effect.remainingSeconds }}s
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="phase !== 'playing'"
                            class="absolute inset-0 flex items-center justify-center bg-[#173d3a]/35 p-5 backdrop-blur-[2px] dark:bg-neutral-950/45"
                        >
                            <div
                                class="pointer-events-auto flex max-w-sm flex-col items-center gap-5 rounded-2xl bg-[#fffaf0]/94 p-6 text-center shadow-2xl ring-1 ring-neutral-950/10 dark:bg-neutral-900/94 dark:shadow-none dark:ring-white/10"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <Gauge
                                        class="size-4 shrink-0 stroke-emerald-700 dark:stroke-emerald-400"
                                        aria-hidden="true"
                                    />
                                    <h2
                                        class="max-w-[24ch] text-2xl font-semibold tracking-tight text-balance"
                                    >
                                        {{ overlayTitle }}
                                    </h2>
                                    <p
                                        class="max-w-[40ch] text-pretty text-neutral-600 sm:text-sm dark:text-neutral-300"
                                    >
                                        {{ overlayDescription }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#df5f48] py-2.5 pr-4 pl-3 font-semibold text-white shadow-lg ring-1 ring-[#df5f48] hover:bg-[#c94f3d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 dark:shadow-none"
                                    @click="runPrimaryAction"
                                >
                                    <Play
                                        v-if="
                                            phase === 'idle' ||
                                            phase === 'paused'
                                        "
                                        class="size-4 h-lh shrink-0 fill-white stroke-white"
                                        aria-hidden="true"
                                    />
                                    <RotateCcw
                                        v-else
                                        class="size-4 h-lh shrink-0 stroke-white"
                                        aria-hidden="true"
                                    />
                                    {{ primaryActionLabel }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 py-4 text-neutral-600 sm:text-sm dark:text-neutral-400"
                >
                    <p class="inline-flex items-center gap-2">
                        <ArrowUp
                            class="size-4 h-lh shrink-0 stroke-current"
                            aria-hidden="true"
                        />
                        Space, click, or tap to flap
                    </p>
                    <p>P to pause</p>
                    <p>M to mute</p>
                </div>

                <p class="sr-only" aria-live="polite">
                    {{ pickupAnnouncement }}
                </p>
            </section>

            <section
                class="flex min-w-0 flex-col gap-4 lg:col-start-1 lg:row-start-2"
                aria-labelledby="power-ups-heading"
            >
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="power-ups-heading" class="text-lg font-semibold">
                        Power-ups in the reeds
                    </h2>
                    <p
                        class="text-neutral-500 sm:text-sm dark:text-neutral-400"
                    >
                        Fly through to collect
                    </p>
                </div>
                <ul
                    class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1"
                    role="list"
                >
                    <li
                        class="flex items-start gap-3 border-t border-neutral-950/10 pt-4 dark:border-white/10"
                    >
                        <ShieldCheck
                            class="size-4 h-lh shrink-0 stroke-emerald-700 dark:stroke-emerald-400"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h3 class="font-medium">Bubble shield</h3>
                            <p
                                class="text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                            >
                                Absorbs one unlucky bump.
                            </p>
                        </div>
                    </li>
                    <li
                        class="flex items-start gap-3 border-t border-neutral-950/10 pt-4 dark:border-white/10"
                    >
                        <TimerReset
                            class="size-4 h-lh shrink-0 stroke-sky-700 dark:stroke-sky-400"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h3 class="font-medium">River time</h3>
                            <p
                                class="text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                            >
                                Slows the wetlands for six seconds.
                            </p>
                        </div>
                    </li>
                    <li
                        class="flex items-start gap-3 border-t border-neutral-950/10 pt-4 dark:border-white/10"
                    >
                        <Sparkles
                            class="size-4 h-lh shrink-0 stroke-amber-700 dark:stroke-amber-400"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h3 class="font-medium">Golden guava</h3>
                            <p
                                class="text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                            >
                                Doubles every gate you clear.
                            </p>
                        </div>
                    </li>
                </ul>
            </section>
        </main>

        <footer
            class="relative border-t border-neutral-950/10 dark:border-white/10"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-4 py-6 text-neutral-600 sm:flex-row sm:items-center sm:px-6 sm:text-sm lg:px-8 dark:text-neutral-400"
            >
                <p>Quick solo runs today. Real-time races are next.</p>
                <p>Made for keyboard, mouse, and touch.</p>
            </div>
        </footer>
    </div>
</template>
