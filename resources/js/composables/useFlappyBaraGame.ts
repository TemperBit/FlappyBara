import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type GamePhase = 'idle' | 'playing' | 'paused' | 'game-over';
export type PowerUpKind = 'shield' | 'slow-time' | 'double-score';

export type ActiveEffect = {
    kind: PowerUpKind;
    label: string;
    remainingSeconds: number;
};

export type Player = {
    x: number;
    y: number;
    velocityY: number;
    rotation: number;
};

type Pickup = {
    kind: PowerUpKind;
    y: number;
    collected: boolean;
};

type Obstacle = {
    id: number;
    x: number;
    gapY: number;
    gapSize: number;
    scored: boolean;
    pickup: Pickup | null;
};

type Particle = {
    x: number;
    y: number;
    velocityX: number;
    velocityY: number;
    radius: number;
    color: string;
    life: number;
};

export type GameSnapshot = {
    phase: GamePhase;
    score: number;
    player: Player;
    obstacles: Array<Pick<Obstacle, 'id' | 'x' | 'gapY' | 'gapSize'>>;
    effects: ActiveEffect[];
    elapsedMilliseconds: number;
};

export type RacePlayerSnapshot = Pick<
    GameSnapshot,
    'phase' | 'score' | 'player' | 'elapsedMilliseconds'
>;

export type RemotePlayer = {
    id: string;
    name: string;
    color: string;
    snapshot: RacePlayerSnapshot;
};

type GameOptions = {
    seed?: number;
    canStart?: () => boolean;
    allowPause?: boolean;
};

const WORLD_WIDTH = 720;
const WORLD_HEIGHT = 900;
const GROUND_Y = 800;
const PLAYER_X = 170;
const COLLISION_RADIUS = 23;
const GRAVITY = 1_360;
const FLAP_VELOCITY = -485;
const OBSTACLE_WIDTH = 104;
const OBSTACLE_SPEED = 205;
const OBSTACLE_INTERVAL = 2.15;
const PICKUP_RADIUS = 29;
const HIGH_SCORE_KEY = 'flappybara.high-score';
const POWER_UP_SEQUENCE: PowerUpKind[] = [
    'shield',
    'slow-time',
    'double-score',
];

const powerUpLabels: Record<PowerUpKind, string> = {
    shield: 'Bubble shield',
    'slow-time': 'River time',
    'double-score': 'Golden guava',
};

const lightPalette = {
    skyTop: '#d8f2ec',
    skyBottom: '#f8e9bd',
    sun: '#f6c85f',
    cloud: '#fffdf3',
    farHill: '#a9d6ad',
    nearHill: '#70ad83',
    water: '#7dc9c5',
    waterLine: '#e9f6e9',
    ground: '#3f7959',
    trunk: '#795644',
    trunkLight: '#a47757',
    moss: '#467a4f',
    mossLight: '#83ae63',
    body: '#a56f4e',
    bodyLight: '#c98f63',
    bodyDark: '#5a3c33',
    scarf: '#e2674e',
};

const darkPalette = {
    skyTop: '#152f3c',
    skyBottom: '#285266',
    sun: '#f2d795',
    cloud: '#d9e5df',
    farHill: '#315f55',
    nearHill: '#244d43',
    water: '#2d6f70',
    waterLine: '#78a9a1',
    ground: '#173f34',
    trunk: '#503b36',
    trunkLight: '#715044',
    moss: '#315d43',
    mossLight: '#60835a',
    body: '#9c674d',
    bodyLight: '#c48a66',
    bodyDark: '#3e2d2c',
    scarf: '#ed8069',
};

export function useFlappyBaraGame(options: GameOptions = {}) {
    const canvasRef = ref<HTMLCanvasElement | null>(null);
    const phase = ref<GamePhase>('idle');
    const score = ref(0);
    const highScore = ref(0);
    const isMuted = ref(false);
    const shieldSeconds = ref(0);
    const slowTimeSeconds = ref(0);
    const doubleScoreSeconds = ref(0);
    const lastPickup = ref<PowerUpKind | null>(null);
    const runNumber = ref(0);

    let player = createPlayer();
    let obstacles: Obstacle[] = [];
    let particles: Particle[] = [];
    let nextObstacleId = 1;
    let spawnTimer = 0;
    let elapsedSeconds = 0;
    let ambientSeconds = 0;
    let lastFrameTime = 0;
    let invincibilitySeconds = 0;
    let shieldCharges = 0;
    let screenShakeSeconds = 0;
    let animationFrame = 0;
    let audioContext: AudioContext | null = null;
    let themeObserver: MutationObserver | null = null;
    let darkMode = false;
    let remotePlayers: RemotePlayer[] = [];

    const activeEffects = computed<ActiveEffect[]>(() => {
        const effects: ActiveEffect[] = [];

        if (shieldSeconds.value > 0 && shieldCharges > 0) {
            effects.push({
                kind: 'shield',
                label: powerUpLabels.shield,
                remainingSeconds: Math.ceil(shieldSeconds.value),
            });
        }

        if (slowTimeSeconds.value > 0) {
            effects.push({
                kind: 'slow-time',
                label: powerUpLabels['slow-time'],
                remainingSeconds: Math.ceil(slowTimeSeconds.value),
            });
        }

        if (doubleScoreSeconds.value > 0) {
            effects.push({
                kind: 'double-score',
                label: powerUpLabels['double-score'],
                remainingSeconds: Math.ceil(doubleScoreSeconds.value),
            });
        }

        return effects;
    });

    const canPause = computed(
        () =>
            options.allowPause !== false &&
            ['playing', 'paused'].includes(phase.value),
    );

    const statusLabel = computed(() => {
        if (phase.value === 'playing') {
            return 'Run in progress';
        }

        if (phase.value === 'paused') {
            return 'Run paused';
        }

        if (phase.value === 'game-over') {
            return 'Run complete';
        }

        return 'Ready to fly';
    });

    function createPlayer(): Player {
        return {
            x: PLAYER_X,
            y: 410,
            velocityY: 0,
            rotation: 0,
        };
    }

    function startGame(): void {
        if (options.canStart?.() === false) {
            return;
        }

        runNumber.value += 1;
        score.value = 0;
        phase.value = 'playing';
        shieldSeconds.value = 0;
        slowTimeSeconds.value = 0;
        doubleScoreSeconds.value = 0;
        lastPickup.value = null;
        shieldCharges = 0;
        invincibilitySeconds = 0;
        screenShakeSeconds = 0;
        elapsedSeconds = 0;
        spawnTimer = 0;
        nextObstacleId = 1;
        player = createPlayer();
        obstacles = [createObstacle(WORLD_WIDTH + 90)];
        particles = [];
        flap();
    }

    function flap(): void {
        if (phase.value === 'idle' || phase.value === 'game-over') {
            startGame();

            return;
        }

        if (phase.value !== 'playing') {
            return;
        }

        player.velocityY = FLAP_VELOCITY;
        createBurst(player.x - 20, player.y + 18, '#f8e0a1', 4);
        playTone(360, 0.055, 'sine');
    }

    function togglePause(): void {
        if (options.allowPause === false) {
            return;
        }

        if (phase.value === 'playing') {
            phase.value = 'paused';

            return;
        }

        if (phase.value === 'paused') {
            phase.value = 'playing';
            lastFrameTime = performance.now();
        }
    }

    function toggleMuted(): void {
        isMuted.value = !isMuted.value;
    }

    function createObstacle(x: number): Obstacle {
        const id = nextObstacleId;
        const gapSize = Math.max(220, 268 - Math.floor(score.value / 6) * 6);
        const verticalRange = GROUND_Y - gapSize - 210;
        const seedOffset = (options.seed ?? 1) % 997;
        const gapY =
            105 +
            gapSize / 2 +
            ((Math.sin((id + seedOffset) * 1.83) + 1) / 2) * verticalRange;
        const shouldHavePickup = id === 1 || id % 2 === 0;
        const pickupKind =
            POWER_UP_SEQUENCE[(id - 1) % POWER_UP_SEQUENCE.length];

        nextObstacleId += 1;

        return {
            id,
            x,
            gapY,
            gapSize,
            scored: false,
            pickup: shouldHavePickup
                ? {
                      kind: pickupKind,
                      y: gapY,
                      collected: false,
                  }
                : null,
        };
    }

    function update(deltaSeconds: number): void {
        elapsedSeconds += deltaSeconds;
        spawnTimer += deltaSeconds;
        invincibilitySeconds = Math.max(0, invincibilitySeconds - deltaSeconds);
        screenShakeSeconds = Math.max(0, screenShakeSeconds - deltaSeconds);
        updateEffectTimers(deltaSeconds);

        player.velocityY += GRAVITY * deltaSeconds;
        player.y += player.velocityY * deltaSeconds;
        player.rotation = Math.max(
            -0.45,
            Math.min(1.05, player.velocityY / 650),
        );

        spawnObstacles();

        const speedMultiplier = slowTimeSeconds.value > 0 ? 0.58 : 1;
        const obstacleMovement =
            OBSTACLE_SPEED * speedMultiplier * deltaSeconds;

        for (const obstacle of obstacles) {
            obstacle.x -= obstacleMovement;
            updatePickup(obstacle);

            if (!obstacle.scored && obstacle.x + OBSTACLE_WIDTH < player.x) {
                obstacle.scored = true;
                score.value += doubleScoreSeconds.value > 0 ? 2 : 1;
                createBurst(player.x + 28, player.y, '#f6c85f', 7);
                playTone(610, 0.075, 'triangle');
            }

            if (invincibilitySeconds === 0 && collidesWithObstacle(obstacle)) {
                handleCollision('obstacle');
            }
        }

        obstacles = obstacles.filter(
            (obstacle) => obstacle.x + OBSTACLE_WIDTH > -30,
        );

        if (player.y - COLLISION_RADIUS < 0) {
            handleCollision('ceiling');
        } else if (player.y + COLLISION_RADIUS > GROUND_Y) {
            handleCollision('ground');
        }

        updateParticles(deltaSeconds);
    }

    function updateEffectTimers(deltaSeconds: number): void {
        shieldSeconds.value = Math.max(0, shieldSeconds.value - deltaSeconds);
        slowTimeSeconds.value = Math.max(
            0,
            slowTimeSeconds.value - deltaSeconds,
        );
        doubleScoreSeconds.value = Math.max(
            0,
            doubleScoreSeconds.value - deltaSeconds,
        );

        if (shieldSeconds.value === 0) {
            shieldCharges = 0;
        }
    }

    function spawnObstacles(): void {
        if (spawnTimer < OBSTACLE_INTERVAL) {
            return;
        }

        spawnTimer -= OBSTACLE_INTERVAL;
        obstacles.push(createObstacle(WORLD_WIDTH + 30));
    }

    function updatePickup(obstacle: Obstacle): void {
        if (obstacle.pickup === null || obstacle.pickup.collected) {
            return;
        }

        const pickupX = obstacle.x + OBSTACLE_WIDTH / 2;
        const distance = Math.hypot(
            pickupX - player.x,
            obstacle.pickup.y - player.y,
        );

        if (distance > PICKUP_RADIUS + COLLISION_RADIUS) {
            return;
        }

        obstacle.pickup.collected = true;
        activatePowerUp(obstacle.pickup.kind);
        createBurst(pickupX, obstacle.pickup.y, '#fff2a6', 18);
        playTone(770, 0.12, 'sine');
    }

    function activatePowerUp(kind: PowerUpKind): void {
        lastPickup.value = kind;

        if (kind === 'shield') {
            shieldCharges = 1;
            shieldSeconds.value = 12;
        } else if (kind === 'slow-time') {
            slowTimeSeconds.value = 6;
        } else {
            doubleScoreSeconds.value = 8;
        }

        window.setTimeout(() => {
            if (lastPickup.value === kind) {
                lastPickup.value = null;
            }
        }, 1_600);
    }

    function collidesWithObstacle(obstacle: Obstacle): boolean {
        const gapTop = obstacle.gapY - obstacle.gapSize / 2;
        const gapBottom = obstacle.gapY + obstacle.gapSize / 2;

        return (
            circleIntersectsRectangle(
                player.x,
                player.y,
                COLLISION_RADIUS,
                obstacle.x,
                0,
                OBSTACLE_WIDTH,
                gapTop,
            ) ||
            circleIntersectsRectangle(
                player.x,
                player.y,
                COLLISION_RADIUS,
                obstacle.x,
                gapBottom,
                OBSTACLE_WIDTH,
                GROUND_Y - gapBottom,
            )
        );
    }

    function circleIntersectsRectangle(
        circleX: number,
        circleY: number,
        radius: number,
        rectangleX: number,
        rectangleY: number,
        rectangleWidth: number,
        rectangleHeight: number,
    ): boolean {
        const nearestX = Math.max(
            rectangleX,
            Math.min(circleX, rectangleX + rectangleWidth),
        );
        const nearestY = Math.max(
            rectangleY,
            Math.min(circleY, rectangleY + rectangleHeight),
        );

        return Math.hypot(circleX - nearestX, circleY - nearestY) < radius;
    }

    function handleCollision(
        collision: 'obstacle' | 'ceiling' | 'ground',
    ): void {
        if (invincibilitySeconds > 0 || phase.value !== 'playing') {
            return;
        }

        if (shieldCharges > 0) {
            shieldCharges = 0;
            shieldSeconds.value = 0;
            invincibilitySeconds = 1.25;
            screenShakeSeconds = 0.3;

            if (collision === 'ground') {
                player.y = GROUND_Y - COLLISION_RADIUS - 12;
                player.velocityY = -330;
            } else if (collision === 'ceiling') {
                player.y = COLLISION_RADIUS + 12;
                player.velocityY = 250;
            } else {
                player.velocityY = -285;
            }

            createBurst(player.x, player.y, '#9be8dc', 24);
            playTone(180, 0.16, 'sawtooth');

            return;
        }

        endGame();
    }

    function endGame(): void {
        phase.value = 'game-over';
        screenShakeSeconds = 0.45;
        highScore.value = Math.max(highScore.value, score.value);
        window.localStorage.setItem(HIGH_SCORE_KEY, String(highScore.value));
        createBurst(player.x, player.y, '#e88168', 20);
        playTone(120, 0.22, 'sawtooth');
    }

    function createBurst(
        x: number,
        y: number,
        color: string,
        count: number,
    ): void {
        for (let index = 0; index < count; index += 1) {
            const angle = (Math.PI * 2 * index) / count + Math.random() * 0.4;
            const speed = 45 + Math.random() * 115;

            particles.push({
                x,
                y,
                velocityX: Math.cos(angle) * speed,
                velocityY: Math.sin(angle) * speed,
                radius: 2.5 + Math.random() * 4.5,
                color,
                life: 0.45 + Math.random() * 0.45,
            });
        }
    }

    function updateParticles(deltaSeconds: number): void {
        for (const particle of particles) {
            particle.x += particle.velocityX * deltaSeconds;
            particle.y += particle.velocityY * deltaSeconds;
            particle.velocityY += 210 * deltaSeconds;
            particle.life -= deltaSeconds;
        }

        particles = particles.filter((particle) => particle.life > 0);
    }

    function render(): void {
        const canvas = canvasRef.value;

        if (canvas === null) {
            return;
        }

        const context = canvas.getContext('2d');

        if (context === null) {
            return;
        }

        const palette = darkMode ? darkPalette : lightPalette;
        context.save();

        if (screenShakeSeconds > 0) {
            context.translate(
                (Math.random() - 0.5) * 10,
                (Math.random() - 0.5) * 10,
            );
        }

        drawBackground(context, palette);

        for (const obstacle of obstacles) {
            drawObstacle(context, obstacle, palette);
        }

        drawParticles(context);

        for (const remotePlayer of remotePlayers) {
            if (remotePlayer.snapshot.phase !== 'idle') {
                drawCapybara(
                    context,
                    palette,
                    remotePlayer.snapshot.player,
                    remotePlayer,
                );
            }
        }

        drawCapybara(context, palette);
        drawForeground(context, palette);
        context.restore();
    }

    function drawBackground(
        context: CanvasRenderingContext2D,
        palette: typeof lightPalette,
    ): void {
        const gradient = context.createLinearGradient(0, 0, 0, GROUND_Y);
        gradient.addColorStop(0, palette.skyTop);
        gradient.addColorStop(1, palette.skyBottom);
        context.fillStyle = gradient;
        context.fillRect(0, 0, WORLD_WIDTH, WORLD_HEIGHT);

        context.globalAlpha = darkMode ? 0.7 : 0.9;
        context.fillStyle = palette.sun;
        context.beginPath();
        context.arc(586, 126, darkMode ? 36 : 52, 0, Math.PI * 2);
        context.fill();
        context.globalAlpha = 1;

        drawCloud(
            context,
            ((ambientSeconds * 11 + 70) % 880) - 130,
            128,
            0.8,
            palette.cloud,
        );
        drawCloud(
            context,
            ((ambientSeconds * 7 + 430) % 980) - 160,
            235,
            1.05,
            palette.cloud,
        );

        context.fillStyle = palette.farHill;
        context.beginPath();
        context.moveTo(0, 585);
        context.quadraticCurveTo(115, 425, 265, 585);
        context.quadraticCurveTo(410, 390, 590, 585);
        context.quadraticCurveTo(665, 505, 720, 535);
        context.lineTo(720, GROUND_Y);
        context.lineTo(0, GROUND_Y);
        context.closePath();
        context.fill();

        context.fillStyle = palette.nearHill;
        context.beginPath();
        context.moveTo(0, 670);
        context.quadraticCurveTo(150, 510, 330, 675);
        context.quadraticCurveTo(500, 535, 720, 665);
        context.lineTo(720, GROUND_Y);
        context.lineTo(0, GROUND_Y);
        context.closePath();
        context.fill();

        context.fillStyle = palette.water;
        context.fillRect(0, 700, WORLD_WIDTH, GROUND_Y - 700);

        context.strokeStyle = palette.waterLine;
        context.lineWidth = 3;
        context.globalAlpha = 0.45;

        for (let index = 0; index < 5; index += 1) {
            const lineX = ((index * 167 - ambientSeconds * 20) % 860) - 80;
            const lineY = 726 + index * 14;
            context.beginPath();
            context.moveTo(lineX, lineY);
            context.lineTo(lineX + 88, lineY);
            context.stroke();
        }

        context.globalAlpha = 1;
    }

    function drawCloud(
        context: CanvasRenderingContext2D,
        x: number,
        y: number,
        scale: number,
        color: string,
    ): void {
        context.save();
        context.translate(x, y);
        context.scale(scale, scale);
        context.globalAlpha = darkMode ? 0.16 : 0.55;
        context.fillStyle = color;
        context.beginPath();
        context.arc(34, 22, 22, 0, Math.PI * 2);
        context.arc(62, 9, 31, 0, Math.PI * 2);
        context.arc(96, 23, 24, 0, Math.PI * 2);
        context.roundRect(30, 22, 76, 32, 16);
        context.fill();
        context.restore();
    }

    function drawObstacle(
        context: CanvasRenderingContext2D,
        obstacle: Obstacle,
        palette: typeof lightPalette,
    ): void {
        const gapTop = obstacle.gapY - obstacle.gapSize / 2;
        const gapBottom = obstacle.gapY + obstacle.gapSize / 2;

        drawTrunk(
            context,
            obstacle.x,
            -30,
            OBSTACLE_WIDTH,
            gapTop + 30,
            false,
            palette,
        );
        drawTrunk(
            context,
            obstacle.x,
            gapBottom,
            OBSTACLE_WIDTH,
            GROUND_Y - gapBottom + 35,
            true,
            palette,
        );

        if (obstacle.pickup !== null && !obstacle.pickup.collected) {
            drawPickup(
                context,
                obstacle.x + OBSTACLE_WIDTH / 2,
                obstacle.pickup.y,
                obstacle.pickup.kind,
            );
        }
    }

    function drawTrunk(
        context: CanvasRenderingContext2D,
        x: number,
        y: number,
        width: number,
        height: number,
        growsUp: boolean,
        palette: typeof lightPalette,
    ): void {
        context.fillStyle = palette.trunk;
        context.beginPath();
        context.roundRect(x, y, width, height, 26);
        context.fill();

        context.fillStyle = palette.trunkLight;
        context.globalAlpha = 0.65;
        context.beginPath();
        context.roundRect(x + 18, y + 8, 17, Math.max(0, height - 16), 9);
        context.fill();
        context.globalAlpha = 1;

        const edgeY = growsUp ? y : y + height;
        context.fillStyle = palette.moss;
        context.beginPath();
        context.ellipse(
            x + width / 2,
            edgeY,
            width / 1.7,
            26,
            0,
            0,
            Math.PI * 2,
        );
        context.fill();
        context.fillStyle = palette.mossLight;
        context.globalAlpha = 0.72;
        context.beginPath();
        context.ellipse(
            x + width / 2 - 8,
            edgeY + (growsUp ? -5 : 5),
            width / 2.7,
            12,
            0,
            0,
            Math.PI * 2,
        );
        context.fill();
        context.globalAlpha = 1;
    }

    function drawPickup(
        context: CanvasRenderingContext2D,
        x: number,
        y: number,
        kind: PowerUpKind,
    ): void {
        const pulse = 1 + Math.sin(ambientSeconds * 5) * 0.06;
        context.save();
        context.translate(x, y);
        context.scale(pulse, pulse);
        context.shadowBlur = 22;
        context.shadowColor =
            kind === 'shield'
                ? '#76ddd0'
                : kind === 'slow-time'
                  ? '#9fc9ff'
                  : '#ffd76e';
        context.fillStyle = darkMode ? '#173c47' : '#fffaf0';
        context.beginPath();
        context.arc(0, 0, PICKUP_RADIUS, 0, Math.PI * 2);
        context.fill();
        context.shadowBlur = 0;
        context.lineWidth = 5;

        if (kind === 'shield') {
            context.strokeStyle = '#3db9ad';
            context.beginPath();
            context.moveTo(0, -15);
            context.lineTo(14, -9);
            context.lineTo(11, 8);
            context.quadraticCurveTo(0, 20, -11, 8);
            context.lineTo(-14, -9);
            context.closePath();
            context.stroke();
        } else if (kind === 'slow-time') {
            context.strokeStyle = '#6598cf';
            context.beginPath();
            context.arc(0, 0, 14, 0, Math.PI * 2);
            context.moveTo(0, 0);
            context.lineTo(0, -9);
            context.moveTo(0, 0);
            context.lineTo(8, 5);
            context.stroke();
        } else {
            context.fillStyle = '#e9a835';
            context.beginPath();
            context.arc(-2, 3, 14, 0, Math.PI * 2);
            context.fill();
            context.fillStyle = '#5c9b54';
            context.beginPath();
            context.ellipse(8, -12, 8, 4, -0.55, 0, Math.PI * 2);
            context.fill();
        }

        context.restore();
    }

    function drawCapybara(
        context: CanvasRenderingContext2D,
        palette: typeof lightPalette,
        renderedPlayer: Player = player,
        remotePlayer?: RemotePlayer,
    ): void {
        const idleBob =
            remotePlayer === undefined && phase.value === 'idle'
                ? Math.sin(ambientSeconds * 2.3) * 9
                : 0;
        const drawY = renderedPlayer.y + idleBob;
        context.save();
        context.translate(renderedPlayer.x, drawY);
        context.rotate(renderedPlayer.rotation);

        if (remotePlayer !== undefined) {
            context.globalAlpha = 0.58;
        }

        if (
            remotePlayer === undefined &&
            shieldCharges > 0 &&
            shieldSeconds.value > 0
        ) {
            context.fillStyle = 'rgba(119, 226, 214, 0.22)';
            context.strokeStyle = 'rgba(134, 245, 232, 0.9)';
            context.lineWidth = 4;
            context.beginPath();
            context.arc(0, 0, 55, 0, Math.PI * 2);
            context.fill();
            context.stroke();
        }

        if (remotePlayer === undefined && invincibilitySeconds > 0) {
            context.globalAlpha =
                Math.floor(invincibilitySeconds * 10) % 2 === 0 ? 0.45 : 1;
        }

        context.fillStyle = remotePlayer?.color ?? palette.scarf;
        context.beginPath();
        context.moveTo(-34, -17);
        context.quadraticCurveTo(-67, -29, -81, -12);
        context.quadraticCurveTo(-60, -3, -36, 3);
        context.closePath();
        context.fill();

        context.fillStyle = palette.body;
        context.beginPath();
        context.ellipse(-6, 5, 48, 34, 0.06, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = palette.bodyLight;
        context.beginPath();
        context.ellipse(28, -9, 30, 31, 0.12, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = palette.body;
        context.beginPath();
        context.arc(14, -34, 10, 0, Math.PI * 2);
        context.arc(38, -32, 9, 0, Math.PI * 2);
        context.fill();

        context.fillStyle = palette.bodyDark;
        context.beginPath();
        context.arc(37, -12, 3.8, 0, Math.PI * 2);
        context.fill();
        context.beginPath();
        context.ellipse(53, 2, 7, 5, 0.05, 0, Math.PI * 2);
        context.fill();

        context.strokeStyle = palette.bodyDark;
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(50, 10);
        context.quadraticCurveTo(44, 15, 38, 12);
        context.stroke();

        context.fillStyle = palette.bodyDark;
        context.beginPath();
        context.roundRect(-28, 26, 13, 16, 6);
        context.roundRect(10, 27, 13, 16, 6);
        context.fill();

        context.restore();

        if (remotePlayer !== undefined) {
            drawRemotePlayerLabel(
                context,
                remotePlayer.name,
                renderedPlayer.x,
                drawY - 58,
            );
        }
    }

    function drawRemotePlayerLabel(
        context: CanvasRenderingContext2D,
        name: string,
        x: number,
        y: number,
    ): void {
        context.save();
        context.font = '600 13px InterVariable, sans-serif';
        context.textAlign = 'center';
        const labelWidth = Math.min(128, context.measureText(name).width + 18);
        context.fillStyle = darkMode
            ? 'rgba(10, 18, 22, 0.78)'
            : 'rgba(255, 250, 240, 0.86)';
        context.beginPath();
        context.roundRect(x - labelWidth / 2, y - 14, labelWidth, 24, 12);
        context.fill();
        context.fillStyle = darkMode ? '#f5f5f4' : '#292524';
        context.fillText(name, x, y + 3, labelWidth - 12);
        context.restore();
    }

    function drawParticles(context: CanvasRenderingContext2D): void {
        for (const particle of particles) {
            context.globalAlpha = Math.min(1, particle.life * 2.2);
            context.fillStyle = particle.color;
            context.beginPath();
            context.arc(
                particle.x,
                particle.y,
                particle.radius,
                0,
                Math.PI * 2,
            );
            context.fill();
        }

        context.globalAlpha = 1;
    }

    function drawForeground(
        context: CanvasRenderingContext2D,
        palette: typeof lightPalette,
    ): void {
        context.fillStyle = palette.ground;
        context.fillRect(0, GROUND_Y, WORLD_WIDTH, WORLD_HEIGHT - GROUND_Y);

        for (let index = 0; index < 24; index += 1) {
            const x = index * 38 - ((ambientSeconds * 26) % 38) - 10;
            const height = 16 + (index % 4) * 9;
            context.strokeStyle =
                index % 2 === 0 ? palette.mossLight : palette.moss;
            context.lineWidth = 5;
            context.beginPath();
            context.moveTo(x, GROUND_Y + 5);
            context.quadraticCurveTo(
                x + 4,
                GROUND_Y - height / 2,
                x + 1,
                GROUND_Y - height,
            );
            context.stroke();
        }
    }

    function playTone(
        frequency: number,
        duration: number,
        type: OscillatorType,
    ): void {
        if (isMuted.value) {
            return;
        }

        const AudioContextConstructor =
            window.AudioContext ??
            (window as Window & { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (AudioContextConstructor === undefined) {
            return;
        }

        audioContext ??= new AudioContextConstructor();

        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        const startTime = audioContext.currentTime;
        oscillator.frequency.setValueAtTime(frequency, startTime);
        oscillator.type = type;
        gain.gain.setValueAtTime(0.045, startTime);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start(startTime);
        oscillator.stop(startTime + duration);
    }

    function resizeCanvas(): void {
        const canvas = canvasRef.value;

        if (canvas === null) {
            return;
        }

        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = WORLD_WIDTH * pixelRatio;
        canvas.height = WORLD_HEIGHT * pixelRatio;
        const context = canvas.getContext('2d');
        context?.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        render();
    }

    function frame(currentTime: number): void {
        if (lastFrameTime === 0) {
            lastFrameTime = currentTime;
        }

        const deltaSeconds = Math.min(
            0.034,
            (currentTime - lastFrameTime) / 1_000,
        );
        lastFrameTime = currentTime;
        ambientSeconds += deltaSeconds;

        if (phase.value === 'playing') {
            update(deltaSeconds);
        } else if (phase.value === 'idle') {
            player.y = 410;
            player.rotation = Math.sin(ambientSeconds * 1.8) * 0.04;
            updateParticles(deltaSeconds);
        } else {
            updateParticles(deltaSeconds);
        }

        render();
        animationFrame = window.requestAnimationFrame(frame);
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (['Space', 'ArrowUp'].includes(event.code)) {
            event.preventDefault();
            flap();
        } else if (event.code === 'KeyP') {
            togglePause();
        } else if (event.code === 'KeyM') {
            toggleMuted();
        }
    }

    function handleVisibilityChange(): void {
        if (document.hidden && phase.value === 'playing') {
            if (options.allowPause === false) {
                endGame();
            } else {
                phase.value = 'paused';
            }
        }
    }

    function setRemotePlayers(players: RemotePlayer[]): void {
        remotePlayers = players;
    }

    function getSnapshot(): GameSnapshot {
        return {
            phase: phase.value,
            score: score.value,
            player: { ...player },
            obstacles: obstacles.map((obstacle) => ({
                id: obstacle.id,
                x: obstacle.x,
                gapY: obstacle.gapY,
                gapSize: obstacle.gapSize,
            })),
            effects: activeEffects.value.map((effect) => ({ ...effect })),
            elapsedMilliseconds: Math.round(elapsedSeconds * 1_000),
        };
    }

    onMounted(() => {
        highScore.value = Number.parseInt(
            window.localStorage.getItem(HIGH_SCORE_KEY) ?? '0',
            10,
        );
        darkMode = document.documentElement.classList.contains('dark');
        themeObserver = new MutationObserver(() => {
            darkMode = document.documentElement.classList.contains('dark');
        });
        themeObserver.observe(document.documentElement, {
            attributeFilter: ['class'],
        });
        resizeCanvas();
        window.addEventListener('keydown', handleKeydown);
        window.addEventListener('resize', resizeCanvas);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        animationFrame = window.requestAnimationFrame(frame);
    });

    onBeforeUnmount(() => {
        window.cancelAnimationFrame(animationFrame);
        window.removeEventListener('keydown', handleKeydown);
        window.removeEventListener('resize', resizeCanvas);
        document.removeEventListener(
            'visibilitychange',
            handleVisibilityChange,
        );
        themeObserver?.disconnect();
        void audioContext?.close();
    });

    return {
        activeEffects,
        canPause,
        canvasRef,
        doubleScoreSeconds,
        flap,
        getSnapshot,
        highScore,
        isMuted,
        lastPickup,
        phase,
        runNumber,
        score,
        setRemotePlayers,
        shieldSeconds,
        slowTimeSeconds,
        startGame,
        statusLabel,
        toggleMuted,
        togglePause,
    };
}
