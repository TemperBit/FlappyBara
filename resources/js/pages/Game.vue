<script setup lang="ts">
import { Form, Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { useConnectionStatus, usePresenceChannel } from '@laravel/echo-vue';
import {
    ArrowUp,
    Check,
    Copy,
    Crown,
    Gauge,
    Pause,
    Play,
    Radio,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Swords,
    TimerReset,
    Trophy,
    Users,
    Volume2,
    VolumeX,
    Wifi,
    WifiOff,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { store as storeGameRun } from '@/actions/App/Http/Controllers/GameRunController';
import {
    start as startRaceRoom,
    store as createRaceRoom,
} from '@/actions/App/Http/Controllers/RaceRoomController';
import { useFlappyBaraGame } from '@/composables/useFlappyBaraGame';
import type {
    RacePlayerSnapshot,
    RemotePlayer,
} from '@/composables/useFlappyBaraGame';
import { home, login, logout, register } from '@/routes';
import { show as showRaceRoom } from '@/routes/races';
import type { User } from '@/types';

type LeaderboardEntry = {
    rank: number;
    player: string;
    score: number;
    mode: 'solo' | 'race';
    durationMilliseconds: number;
    playedAt: string;
};

type RaceRoom = {
    code: string;
    seed: number;
    startsAt: string | null;
    isHost: boolean;
    hostName: string;
    player: RacePlayer;
};

type RacePlayer = {
    id: string;
    name: string;
    isGuest: boolean;
};

type RaceMember = {
    id: string;
    name: string;
    isHost: boolean;
};

type SnapshotPayload = {
    playerId: string;
    name: string;
    snapshot: RacePlayerSnapshot;
};

type RaceStartedPayload = {
    code: string;
    seed: number;
    startsAt: string;
};

type RaceFinishedPayload = {
    playerId: string;
    playerName: string;
    score: number;
    durationMilliseconds: number;
};

type RunSubmission = {
    score: number;
    durationMilliseconds: number;
    raceCode: string | null;
};

type RunResponse = {
    run: {
        id: number;
        score: number;
        mode: 'solo' | 'race';
    };
    leaderboard: LeaderboardEntry[];
};

const props = withDefaults(
    defineProps<{
        leaderboard: LeaderboardEntry[];
        race?: RaceRoom | null;
    }>(),
    {
        race: null,
    },
);

const page = usePage();
const currentUser = computed(() => page.props.auth.user as User | null);
const currentRacePlayer = computed(() => props.race?.player ?? null);
const leaderboardEntries = ref([...props.leaderboard]);
const raceMembers = ref<RaceMember[]>([]);
const raceFinishers = ref<RaceFinishedPayload[]>([]);
const remoteSnapshots = new Map<string, SnapshotPayload>();
const raceStartsAt = ref(props.race?.startsAt ?? null);
const raceCanStart = ref(props.race === null);
const countdownSeconds = ref<number | null>(null);
const copiedInvite = ref(false);
const joinCode = ref('');
const connectionStatus = props.race
    ? useConnectionStatus()
    : ref<'disconnected'>('disconnected');

let countdownTimer: number | null = null;
let snapshotTimer: number | null = null;
let copiedTimer: number | null = null;
let lastSubmittedRun = 0;

const {
    activeEffects,
    canPause,
    canvasRef,
    flap,
    highScore,
    isMuted,
    lastPickup,
    phase,
    runNumber,
    score,
    setRemotePlayers,
    startGame,
    statusLabel,
    toggleMuted,
    togglePause,
    getSnapshot,
} = useFlappyBaraGame({
    seed: props.race?.seed,
    canStart: () => raceCanStart.value,
    allowPause: props.race === null,
});

const runSubmission = useHttp<RunSubmission, RunResponse>({
    score: 0,
    durationMilliseconds: 0,
    raceCode: props.race?.code ?? null,
});

const raceStartRequest = useHttp<Record<string, never>, { race: RaceRoom }>({});

const presence = props.race
    ? usePresenceChannel<'reverb'>(`race.${props.race.code}`)
    : null;

const remoteColors = ['#3b82f6', '#8b5cf6', '#14b8a6', '#f59e0b', '#ec4899'];

const displayStatusLabel = computed(() => {
    if (props.race === null) {
        return statusLabel.value;
    }

    if (countdownSeconds.value !== null && countdownSeconds.value > 0) {
        return `Race starts in ${countdownSeconds.value}`;
    }

    if (raceStartsAt.value === null) {
        return props.race.isHost ? 'Room ready to start' : 'Waiting for host';
    }

    return statusLabel.value.replace('Run', 'Race');
});

const connectionLabel = computed(() => {
    if (connectionStatus.value === 'connected') {
        return 'Live connection';
    }

    if (['connecting', 'reconnecting'].includes(connectionStatus.value)) {
        return 'Connecting';
    }

    return 'Connection offline';
});

const overlayTitle = computed(() => {
    if (phase.value === 'paused') {
        return 'Catch your breath';
    }

    if (phase.value === 'game-over') {
        if (props.race !== null) {
            return 'Race complete';
        }

        return score.value === highScore.value && score.value > 0
            ? 'A new best run'
            : 'Bara took a dip';
    }

    if (props.race !== null && raceStartsAt.value === null) {
        return 'Gather your herd';
    }

    if (props.race !== null && countdownSeconds.value !== null) {
        return 'Paws ready';
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

    if (props.race !== null && raceStartsAt.value === null) {
        return props.race.isHost
            ? 'Share the invite, then start when everyone appears in the room.'
            : `${props.race.hostName} is hosting. The race starts when they give the signal.`;
    }

    if (props.race !== null && countdownSeconds.value !== null) {
        return `The river opens in ${countdownSeconds.value} ${countdownSeconds.value === 1 ? 'second' : 'seconds'}.`;
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

function normalizeMember(member: RaceMember): RaceMember {
    return {
        id: String(member.id),
        name: member.name,
        isHost: Boolean(member.isHost),
    };
}

function addRaceMember(member: RaceMember): void {
    const normalizedMember = normalizeMember(member);

    raceMembers.value = [
        ...raceMembers.value.filter(
            (existingMember) => existingMember.id !== normalizedMember.id,
        ),
        normalizedMember,
    ].sort((firstMember, secondMember) => {
        if (firstMember.isHost !== secondMember.isHost) {
            return firstMember.isHost ? -1 : 1;
        }

        return firstMember.name.localeCompare(secondMember.name);
    });
}

function removeRaceMember(member: RaceMember): void {
    const memberId = String(member.id);
    raceMembers.value = raceMembers.value.filter(
        (existingMember) => existingMember.id !== memberId,
    );
    remoteSnapshots.delete(memberId);
    syncRemotePlayers();
}

function syncRemotePlayers(): void {
    const players: RemotePlayer[] = [...remoteSnapshots.values()].map(
        (payload) => ({
            id: payload.playerId,
            name: payload.name,
            color: remoteColorFor(payload.playerId),
            snapshot: payload.snapshot,
        }),
    );

    setRemotePlayers(players);
}

function receiveSnapshot(payload: SnapshotPayload): void {
    if (payload.playerId === currentRacePlayer.value?.id) {
        return;
    }

    remoteSnapshots.set(payload.playerId, payload);
    syncRemotePlayers();
}

function remoteColorFor(playerId: string): string {
    let colorIndex = 0;

    for (const character of playerId) {
        colorIndex = (colorIndex * 31 + character.charCodeAt(0)) >>> 0;
    }

    return remoteColors[colorIndex % remoteColors.length];
}

function recordFinisher(payload: RaceFinishedPayload): void {
    raceFinishers.value = [
        ...raceFinishers.value.filter(
            (finisher) => finisher.playerId !== payload.playerId,
        ),
        payload,
    ].sort(
        (firstFinisher, secondFinisher) =>
            secondFinisher.score - firstFinisher.score ||
            firstFinisher.durationMilliseconds -
                secondFinisher.durationMilliseconds,
    );
}

function scheduleRaceStart(startsAt: string): void {
    raceStartsAt.value = startsAt;

    if (countdownTimer !== null) {
        window.clearInterval(countdownTimer);
    }

    const updateCountdown = (): void => {
        const remainingMilliseconds = Date.parse(startsAt) - Date.now();

        if (remainingMilliseconds <= 0) {
            countdownSeconds.value = 0;
            raceCanStart.value = true;

            if (countdownTimer !== null) {
                window.clearInterval(countdownTimer);
                countdownTimer = null;
            }

            if (phase.value === 'idle') {
                startGame();
            }

            return;
        }

        countdownSeconds.value = Math.ceil(remainingMilliseconds / 1_000);
    };

    updateCountdown();
    countdownTimer = window.setInterval(updateCountdown, 100);
}

function requestRaceStart(): void {
    if (props.race === null) {
        return;
    }

    raceStartRequest.post(startRaceRoom.url(props.race.code), {
        onSuccess: ({ race }) => {
            if (race.startsAt !== null) {
                scheduleRaceStart(race.startsAt);
            }
        },
    });
}

async function copyInviteLink(): Promise<void> {
    if (navigator.clipboard !== undefined) {
        await navigator.clipboard.writeText(window.location.href);
    } else {
        const temporaryInput = document.createElement('textarea');
        temporaryInput.value = window.location.href;
        temporaryInput.setAttribute('readonly', '');
        temporaryInput.className = 'fixed opacity-0';
        document.body.appendChild(temporaryInput);
        temporaryInput.select();
        document.execCommand('copy');
        temporaryInput.remove();
    }

    copiedInvite.value = true;

    if (copiedTimer !== null) {
        window.clearTimeout(copiedTimer);
    }

    copiedTimer = window.setTimeout(() => {
        copiedInvite.value = false;
    }, 2_000);
}

function joinRace(): void {
    const normalizedCode = joinCode.value.trim().toUpperCase();

    if (normalizedCode.length === 6) {
        router.visit(showRaceRoom(normalizedCode));
    }
}

function handleLogout(): void {
    router.flushAll();
}

function formatDuration(milliseconds: number): string {
    return `${(milliseconds / 1_000).toFixed(1)}s`;
}

function handleCanvasPointer(event: PointerEvent): void {
    event.preventDefault();
    canvasRef.value?.focus();
    flap();
}

watch(phase, (nextPhase) => {
    if (nextPhase !== 'game-over' || lastSubmittedRun === runNumber.value) {
        return;
    }

    lastSubmittedRun = runNumber.value;
    const snapshot = getSnapshot();

    if (
        props.race !== null &&
        currentRacePlayer.value !== null &&
        presence !== null
    ) {
        const finisher = {
            playerId: currentRacePlayer.value.id,
            playerName: currentRacePlayer.value.name,
            score: snapshot.score,
            durationMilliseconds: snapshot.elapsedMilliseconds,
        } satisfies RaceFinishedPayload;

        recordFinisher(finisher);
        presence.channel().whisper('finish', finisher);
    }

    if (currentUser.value === null) {
        return;
    }

    runSubmission.score = snapshot.score;
    runSubmission.durationMilliseconds = snapshot.elapsedMilliseconds;
    runSubmission.raceCode = props.race?.code ?? null;

    void runSubmission.post(storeGameRun.url(), {
        onSuccess: (response) => {
            leaderboardEntries.value = response.leaderboard;
        },
    });
});

onMounted(() => {
    if (props.race === null || presence === null) {
        return;
    }

    presence
        .channel()
        .here((members: RaceMember[]) => {
            raceMembers.value = [];
            members.forEach(addRaceMember);

            if (
                currentRacePlayer.value !== null &&
                !raceMembers.value.some(
                    (member) => member.id === currentRacePlayer.value!.id,
                )
            ) {
                addRaceMember({
                    id: currentRacePlayer.value.id,
                    name: currentRacePlayer.value.name,
                    isHost: props.race!.isHost,
                });
            }
        })
        .joining(addRaceMember)
        .leaving(removeRaceMember)
        .listen('.race.started', (payload: RaceStartedPayload) => {
            scheduleRaceStart(payload.startsAt);
        })
        .listen('.race.finished', (payload: RaceFinishedPayload) => {
            recordFinisher(payload);
        })
        .listenForWhisper('snapshot', receiveSnapshot)
        .listenForWhisper('finish', recordFinisher);

    snapshotTimer = window.setInterval(() => {
        if (phase.value !== 'playing' || currentRacePlayer.value === null) {
            return;
        }

        const snapshot = getSnapshot();
        presence.channel().whisper('snapshot', {
            playerId: currentRacePlayer.value.id,
            name: currentRacePlayer.value.name,
            snapshot: {
                phase: snapshot.phase,
                score: snapshot.score,
                player: snapshot.player,
                elapsedMilliseconds: snapshot.elapsedMilliseconds,
            },
        } satisfies SnapshotPayload);
    }, 90);

    if (raceStartsAt.value !== null) {
        scheduleRaceStart(raceStartsAt.value);
    }
});

onBeforeUnmount(() => {
    if (countdownTimer !== null) {
        window.clearInterval(countdownTimer);
    }

    if (snapshotTimer !== null) {
        window.clearInterval(snapshotTimer);
    }

    if (copiedTimer !== null) {
        window.clearTimeout(copiedTimer);
    }
});
</script>

<template>
    <Head title="Play">
        <meta
            head-key="description"
            name="description"
            content="Play FlappyBara, race friends live, and climb the global leaderboard."
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
                        :href="logout()"
                        as="button"
                        class="relative rounded-lg px-3 py-2 font-medium text-neutral-700 ring-1 ring-neutral-950/10 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-200 dark:ring-white/10 dark:hover:bg-white/5"
                        data-test="logout-button"
                        @click="handleLogout"
                    >
                        Log out
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
                        {{ race ? 'Live race room' : 'Solo and live races' }}
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
                            Play solo or race friends live with no account. Sign
                            in only when you want to save leaderboard scores.
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

                <div
                    v-if="race"
                    class="flex flex-col gap-4 border-t border-neutral-950/10 pt-5 dark:border-white/10"
                    aria-labelledby="race-room-heading"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2
                                id="race-room-heading"
                                class="text-lg font-semibold"
                            >
                                Race room {{ race.code }}
                            </h2>
                            <p
                                class="text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                            >
                                Hosted by {{ race.hostName }}. Share this page
                                to bring friends straight in.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="relative inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 font-medium text-neutral-700 ring-1 ring-neutral-950/10 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-200 dark:ring-white/10 dark:hover:bg-white/5"
                            @click="copyInviteLink"
                        >
                            <Check
                                v-if="copiedInvite"
                                class="size-4 h-lh shrink-0 stroke-emerald-600 dark:stroke-emerald-400"
                                aria-hidden="true"
                            />
                            <Copy
                                v-else
                                class="size-4 h-lh shrink-0 stroke-current"
                                aria-hidden="true"
                            />
                            {{ copiedInvite ? 'Copied' : 'Copy invite' }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div
                            class="flex items-center gap-2 rounded-full bg-white/55 py-1.5 pr-3 pl-1.5 ring-1 ring-neutral-950/10 dark:bg-white/5 dark:ring-white/10"
                        >
                            <Wifi
                                v-if="connectionStatus === 'connected'"
                                class="size-4 shrink-0 stroke-emerald-700 dark:stroke-emerald-400"
                                aria-hidden="true"
                            />
                            <WifiOff
                                v-else
                                class="size-4 shrink-0 stroke-amber-700 dark:stroke-amber-400"
                                aria-hidden="true"
                            />
                            <p class="sm:text-sm">{{ connectionLabel }}</p>
                        </div>
                        <div
                            class="flex items-center gap-2 rounded-full bg-white/55 py-1.5 pr-3 pl-1.5 ring-1 ring-neutral-950/10 dark:bg-white/5 dark:ring-white/10"
                        >
                            <Users
                                class="size-4 shrink-0 stroke-current"
                                aria-hidden="true"
                            />
                            <p class="tabular-nums sm:text-sm">
                                {{ raceMembers.length }}
                                {{
                                    raceMembers.length === 1
                                        ? 'racer'
                                        : 'racers'
                                }}
                            </p>
                        </div>
                    </div>

                    <ul class="flex flex-wrap gap-2" role="list">
                        <li
                            v-for="member in raceMembers"
                            :key="member.id"
                            class="flex items-center gap-2 rounded-full bg-[#e7dcc0] py-1.5 pr-3 pl-1.5 text-neutral-700 ring-1 ring-neutral-950/5 sm:text-sm dark:bg-white/8 dark:text-neutral-200 dark:ring-white/5"
                        >
                            <Crown
                                v-if="member.isHost"
                                class="size-4 shrink-0 stroke-amber-700 dark:stroke-amber-400"
                                aria-label="Host"
                            />
                            <span
                                v-else
                                class="size-2 shrink-0 rounded-full bg-emerald-600"
                                aria-hidden="true"
                            />
                            {{ member.name }}
                        </li>
                    </ul>

                    <ol
                        v-if="raceFinishers.length > 0"
                        class="flex flex-col divide-y divide-neutral-950/10 border-t border-neutral-950/10 dark:divide-white/10 dark:border-white/10"
                    >
                        <li
                            v-for="(finisher, index) in raceFinishers"
                            :key="finisher.playerId"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <p class="min-w-0 truncate font-medium">
                                {{ index + 1 }}. {{ finisher.playerName }}
                            </p>
                            <p class="shrink-0 tabular-nums sm:text-sm">
                                {{ finisher.score }} gates ·
                                {{
                                    formatDuration(
                                        finisher.durationMilliseconds,
                                    )
                                }}
                            </p>
                        </li>
                    </ol>
                </div>

                <div
                    v-else
                    class="flex flex-col gap-4 border-t border-neutral-950/10 pt-5 dark:border-white/10"
                    aria-labelledby="multiplayer-heading"
                >
                    <div class="flex items-start gap-3">
                        <Swords
                            class="size-4 h-lh shrink-0 stroke-emerald-700 dark:stroke-emerald-400"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <h2
                                id="multiplayer-heading"
                                class="text-lg font-semibold"
                            >
                                Race your herd
                            </h2>
                            <p
                                class="text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                            >
                                Create a live room, send one invite link, and
                                see every Bara fly in real time.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <Form
                            :action="createRaceRoom()"
                            v-slot="{ processing }"
                        >
                            <button
                                type="submit"
                                class="relative inline-flex items-center gap-2 rounded-lg px-3 py-2 font-medium text-neutral-800 ring-1 ring-neutral-950/15 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60 sm:text-sm dark:text-neutral-100 dark:ring-white/15 dark:hover:bg-white/5"
                                :disabled="processing"
                            >
                                <Radio
                                    class="size-4 h-lh shrink-0 stroke-current"
                                    aria-hidden="true"
                                />
                                {{
                                    processing
                                        ? 'Opening room'
                                        : 'Create race room'
                                }}
                            </button>
                        </Form>

                        <form
                            class="flex max-w-xs items-center gap-2"
                            @submit.prevent="joinRace"
                        >
                            <label for="join-code" class="sr-only"
                                >Race invite code</label
                            >
                            <input
                                id="join-code"
                                v-model="joinCode"
                                name="race_code"
                                type="text"
                                autocomplete="off"
                                maxlength="6"
                                placeholder="Invite code"
                                class="min-w-0 flex-1 rounded-lg bg-white/65 px-3 py-2 uppercase ring-1 ring-neutral-950/10 outline-none placeholder:normal-case focus-visible:outline-2 focus-visible:-outline-offset-1 focus-visible:outline-emerald-600 sm:text-sm dark:bg-white/5 dark:ring-white/10"
                            />
                            <button
                                type="submit"
                                class="relative shrink-0 rounded-lg px-3 py-2 font-medium text-neutral-700 ring-1 ring-neutral-950/10 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-40 sm:text-sm dark:text-neutral-200 dark:ring-white/10 dark:hover:bg-white/5"
                                :disabled="joinCode.trim().length !== 6"
                            >
                                Join
                            </button>
                        </form>
                        <p
                            v-if="!currentUser"
                            class="text-neutral-500 sm:text-sm dark:text-neutral-400"
                        >
                            No account required. We will give you a temporary
                            guest name for the race.
                        </p>
                    </div>
                </div>
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
                                {{ displayStatusLabel }}
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
                                v-if="race === null"
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
                                    v-if="race === null"
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

                                <button
                                    v-else-if="
                                        phase === 'idle' &&
                                        raceStartsAt === null &&
                                        race.isHost
                                    "
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#df5f48] py-2.5 pr-4 pl-3 font-semibold text-white shadow-lg ring-1 ring-[#df5f48] hover:bg-[#c94f3d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 disabled:cursor-wait disabled:opacity-70 dark:shadow-none"
                                    :disabled="raceStartRequest.processing"
                                    @click="requestRaceStart"
                                >
                                    <Swords
                                        class="size-4 h-lh shrink-0 stroke-white"
                                        aria-hidden="true"
                                    />
                                    {{
                                        raceStartRequest.processing
                                            ? 'Starting race'
                                            : 'Start race'
                                    }}
                                </button>

                                <p
                                    v-else-if="
                                        phase === 'idle' &&
                                        countdownSeconds !== null
                                    "
                                    class="text-5xl font-semibold tracking-tight tabular-nums"
                                    aria-live="polite"
                                >
                                    {{ countdownSeconds || 'Go' }}
                                </p>

                                <div
                                    v-else-if="
                                        phase === 'idle' &&
                                        raceStartsAt === null
                                    "
                                    class="flex items-center gap-2 text-neutral-600 sm:text-sm dark:text-neutral-300"
                                >
                                    <Radio
                                        class="size-4 h-lh shrink-0 stroke-emerald-700 dark:stroke-emerald-400"
                                        aria-hidden="true"
                                    />
                                    Waiting for {{ race.hostName }}
                                </div>

                                <Link
                                    v-else-if="phase === 'game-over'"
                                    :href="home()"
                                    class="relative inline-flex items-center gap-2 rounded-xl bg-[#df5f48] py-2.5 pr-4 pl-3 font-semibold text-white shadow-lg ring-1 ring-[#df5f48] hover:bg-[#c94f3d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300 dark:shadow-none"
                                >
                                    <RotateCcw
                                        class="size-4 h-lh shrink-0 stroke-white"
                                        aria-hidden="true"
                                    />
                                    Back to practice
                                </Link>
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
                    <p v-if="race === null">P to pause</p>
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

        <section
            class="relative border-t border-neutral-950/10 py-14 sm:py-16 dark:border-white/10"
            aria-labelledby="leaderboard-heading"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                >
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <Trophy
                                class="size-4 h-lh shrink-0 stroke-amber-700 dark:stroke-amber-400"
                                aria-hidden="true"
                            />
                            <p
                                class="font-medium text-amber-800 sm:text-sm dark:text-amber-300"
                            >
                                Global leaderboard
                            </p>
                        </div>
                        <h2
                            id="leaderboard-heading"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            The fastest paws on the river
                        </h2>
                        <p
                            class="max-w-[54ch] text-pretty text-neutral-600 sm:text-sm dark:text-neutral-400"
                        >
                            Sign in and finish a run to put your score on the
                            board. Race and solo scores share the same trail.
                        </p>
                    </div>

                    <p
                        v-if="currentUser"
                        class="text-neutral-600 sm:text-sm dark:text-neutral-400"
                    >
                        Playing as {{ currentUser.name }}
                    </p>
                    <Link
                        v-else
                        :href="register()"
                        class="relative w-fit rounded-lg px-3 py-2 font-medium text-neutral-800 ring-1 ring-neutral-950/15 hover:bg-white/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-sm dark:text-neutral-100 dark:ring-white/15 dark:hover:bg-white/5"
                    >
                        Join the leaderboard
                        <span
                            class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden"
                            aria-hidden="true"
                        />
                    </Link>
                </div>

                <div
                    v-if="leaderboardEntries.length > 0"
                    class="-mx-4 -my-2 overflow-x-auto pt-10 whitespace-nowrap sm:-mx-6 lg:-mx-8"
                    aria-live="polite"
                >
                    <div
                        class="inline-block min-w-full px-4 py-2 align-middle sm:px-6 lg:px-8"
                    >
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="border-b border-neutral-950/10 text-left text-neutral-500 dark:border-white/10 dark:text-neutral-400"
                                >
                                    <th
                                        scope="col"
                                        class="w-16 py-3 pr-4 font-medium whitespace-nowrap sm:text-sm"
                                    >
                                        Rank
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-3 pr-4 font-medium whitespace-nowrap sm:text-sm"
                                    >
                                        Player
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-3 pr-4 font-medium whitespace-nowrap sm:text-sm"
                                    >
                                        Run
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-3 pr-4 text-right font-medium whitespace-nowrap sm:text-sm"
                                    >
                                        Time
                                    </th>
                                    <th
                                        scope="col"
                                        class="py-3 text-right font-medium whitespace-nowrap sm:text-sm"
                                    >
                                        Score
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-neutral-950/10 dark:divide-white/10"
                            >
                                <tr
                                    v-for="entry in leaderboardEntries"
                                    :key="`${entry.rank}-${entry.player}-${entry.playedAt}`"
                                >
                                    <td
                                        class="py-4 pr-4 text-neutral-500 tabular-nums dark:text-neutral-400"
                                    >
                                        {{ entry.rank }}
                                    </td>
                                    <td class="py-4 pr-4 font-medium">
                                        {{ entry.player }}
                                    </td>
                                    <td
                                        class="py-4 pr-4 text-neutral-600 dark:text-neutral-400"
                                    >
                                        {{
                                            entry.mode === 'race'
                                                ? 'Live race'
                                                : 'Solo'
                                        }}
                                    </td>
                                    <td
                                        class="py-4 pr-4 text-right text-neutral-600 tabular-nums dark:text-neutral-400"
                                    >
                                        {{
                                            formatDuration(
                                                entry.durationMilliseconds,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="py-4 text-right text-lg font-semibold tabular-nums"
                                    >
                                        {{ entry.score }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-else
                    class="flex items-start gap-3 border-t border-neutral-950/10 pt-6 dark:border-white/10"
                >
                    <Trophy
                        class="size-4 h-lh shrink-0 stroke-neutral-500"
                        aria-hidden="true"
                    />
                    <p
                        class="text-pretty text-neutral-600 dark:text-neutral-400"
                    >
                        The board is wide open. Finish the first signed-in run
                        to claim the top spot.
                    </p>
                </div>
            </div>
        </section>

        <footer
            class="relative border-t border-neutral-950/10 dark:border-white/10"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col justify-between gap-2 px-4 py-6 text-neutral-600 sm:flex-row sm:items-center sm:px-6 sm:text-sm lg:px-8 dark:text-neutral-400"
            >
                <p>Solo practice and Reverb-powered live races.</p>
                <p>Made for keyboard, mouse, and touch.</p>
            </div>
        </footer>
    </div>
</template>
