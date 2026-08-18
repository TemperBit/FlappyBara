<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        viewBox="0 0 64 64"
        :class="className"
        aria-hidden="true"
        focusable="false"
        v-bind="$attrs"
    >
        <rect
            width="64"
            height="64"
            rx="16"
            class="fill-[#d8f2ec] dark:fill-[#152f3c]"
        />
        <path d="M8 49C19 44 38 45 56 51V64H8V49Z" class="fill-[#70ad83]" />
        <path
            d="M12 48C22 45 41 47 55 54L52 61C37 55 23 54 13 57L12 48Z"
            class="fill-[#df5f48]"
        />
        <circle cx="21" cy="20" r="8" class="fill-[#855a42]" />
        <circle cx="43" cy="19" r="7" class="fill-[#855a42]" />
        <ellipse cx="31" cy="35" rx="22" ry="20" class="fill-[#a87550]" />
        <ellipse cx="43" cy="39" rx="15" ry="12" class="fill-[#c98f63]" />
        <circle cx="37" cy="30" r="2.5" class="fill-[#342720]" />
        <ellipse cx="53" cy="39" rx="4" ry="3.5" class="fill-[#4f352c]" />
        <path
            d="M50 45C46 48 41 47 39 45"
            class="fill-none stroke-[#4f352c] stroke-[2.5]"
            stroke-linecap="round"
        />
        <path
            d="M17 35C15 39 15 43 17 47"
            class="fill-none stroke-[#c98f63] stroke-3"
            stroke-linecap="round"
        />
    </svg>
</template>
