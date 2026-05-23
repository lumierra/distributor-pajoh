<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import {
    Bell,
    ChevronDown,
    LogOut,
    Menu as MenuIcon,
    UserCog,
} from "@lucide/vue";
import { computed, ref, watch } from "vue";
import { toast, Toaster } from "vue-sonner";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/Components/ui/sheet";
import { iconFor } from "@/lib/iconResolver";

const page = usePage();

const user = computed(() => page.props.auth?.user || null);
const companyName = computed(
    () => page.props.company?.name || "Pajoh Distributor",
);
const menuTree = computed(() => page.props.menuTree || []);
const currentComponent = computed(() => page.component);

const currentUrl = computed(() => page.url || "");
const currentRouteName = computed(() => {
    try {
        return route().current() || "";
    } catch (e) {
        return "";
    }
});

const mobileMenuOpen = ref(false);

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    },
    { immediate: true, deep: true },
);

function userInitials(name) {
    if (!name) return "?";
    const parts = String(name).trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function routeOrNull(name) {
    if (!name) return null;
    try {
        return route(name);
    } catch (e) {
        return null;
    }
}

function isActive(menuRoute) {
    if (!menuRoute) return false;
    const target = routeOrNull(menuRoute);
    if (!target) return false;

    if (currentRouteName.value === menuRoute) return true;

    try {
        const targetPath = new URL(target).pathname;
        const currentPath = new URL(currentUrl.value, window.location.origin)
            .pathname;
        return (
            currentPath === targetPath ||
            currentPath.startsWith(`${targetPath}/`)
        );
    } catch (e) {
        return false;
    }
}

function isGroupActive(parent) {
    if (parent.children?.some((child) => isActive(child.route))) return true;
    return isActive(parent.route);
}

function logout() {
    router.post(route("logout"));
}
</script>

<template>
    <div class="min-h-screen bg-background text-foreground antialiased">
        <!-- Top-bar hijau pos-pajoh -->
        <header class="bg-topbar text-white sticky top-0 z-40 shadow-sm">
            <div class="flex h-14 items-center gap-1 px-3 lg:px-6 w-full">
                <!-- Logo Pajoh -->
                <Link
                    :href="routeOrNull('dashboard') || '/'"
                    class="flex items-center gap-2 mr-3 shrink-0 group"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground font-bold text-sm shadow-md ring-2 ring-white/10 group-hover:scale-105 transition-transform"
                    >
                        P
                    </div>
                    <span
                        class="hidden sm:inline font-semibold tracking-tight text-white text-sm"
                    >
                        Pajoh
                    </span>
                </Link>

                <!-- Desktop nav with pill active state -->
                <nav class="hidden lg:flex items-center gap-0.5 flex-1 min-w-0">
                    <template v-for="item in menuTree" :key="item.code">
                        <Link
                            v-if="!item.children?.length"
                            :href="routeOrNull(item.route) || '#'"
                            :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[13px] font-medium whitespace-nowrap transition-all',
                                isActive(item.route)
                                    ? 'bg-white text-[#104837] shadow-sm'
                                    : 'text-white/85 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <component
                                v-if="iconFor(item.icon)"
                                :is="iconFor(item.icon)"
                                class="size-3.5"
                            />
                            {{ item.label }}
                        </Link>

                        <DropdownMenu v-else>
                            <DropdownMenuTrigger
                                :class="[
                                    'group/trigger inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[13px] font-medium whitespace-nowrap transition-all outline-none',
                                    isGroupActive(item)
                                        ? 'bg-white text-[#104837] shadow-sm'
                                        : 'text-white/85 hover:bg-white/10 hover:text-white',
                                ]"
                            >
                                <component
                                    v-if="iconFor(item.icon)"
                                    :is="iconFor(item.icon)"
                                    class="size-3.5"
                                />
                                {{ item.label }}
                                <ChevronDown
                                    class="size-3 opacity-80 transition-transform duration-200 group-data-[state=open]/trigger:rotate-180"
                                />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                :side-offset="6"
                                class="min-w-[14rem] rounded-lg p-1.5 shadow-lg"
                            >
                                <DropdownMenuGroup>
                                    <DropdownMenuItem
                                        v-for="child in item.children"
                                        :key="child.code"
                                        as-child
                                        class="rounded-md py-1.5 px-2 cursor-pointer"
                                    >
                                        <Link
                                            :href="
                                                routeOrNull(child.route) || '#'
                                            "
                                            :class="[
                                                'flex w-full items-center gap-2.5 text-sm',
                                                isActive(child.route) &&
                                                    'text-primary font-medium',
                                            ]"
                                        >
                                            <component
                                                v-if="iconFor(child.icon)"
                                                :is="iconFor(child.icon)"
                                                :class="[
                                                    'size-4 shrink-0',
                                                    isActive(child.route)
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                ]"
                                            />
                                            <span>{{ child.label }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </nav>

                <div class="ml-auto flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        class="relative inline-flex h-8 w-8 items-center justify-center rounded-md text-white/90 hover:bg-white/10 hover:text-white transition-colors"
                        aria-label="Notifikasi"
                    >
                        <Bell class="size-4" />
                    </button>

                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="group/usrtg inline-flex items-center gap-1.5 pl-1 pr-2 h-8 rounded-md text-white/90 hover:bg-white/10 hover:text-white transition-colors outline-none"
                        >
                            <div
                                class="size-6 rounded-md bg-warning text-white flex items-center justify-center text-[10px] font-semibold shadow-sm"
                            >
                                {{ userInitials(user?.name) }}
                            </div>
                            <span
                                class="hidden sm:inline text-[13px] font-medium"
                            >
                                {{ user?.name?.split(" ")[0] }}
                            </span>
                            <ChevronDown
                                class="hidden sm:block size-3 opacity-80 transition-transform duration-200 group-data-[state=open]/usrtg:rotate-180"
                            />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="10"
                            class="min-w-[16rem] rounded-lg p-1.5 shadow-lg"
                        >
                            <DropdownMenuLabel
                                class="px-2.5 py-2 flex flex-col gap-0.5"
                            >
                                <span class="text-sm font-semibold">{{
                                    user?.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ user?.username }} ·
                                    <span class="capitalize">{{
                                        user?.role
                                    }}</span>
                                </span>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    as-child
                                    class="rounded-md py-2 px-2.5 cursor-pointer"
                                >
                                    <Link
                                        :href="
                                            routeOrNull('profile.show') || '#'
                                        "
                                        class="flex w-full items-center gap-2"
                                    >
                                        <UserCog
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span>Profil Saya</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    as-child
                                    class="rounded-md py-2 px-2.5 cursor-pointer"
                                >
                                    <Link
                                        :href="
                                            routeOrNull(
                                                'password.change.show',
                                            ) || '#'
                                        "
                                        class="flex w-full items-center gap-2"
                                    >
                                        <UserCog
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span>Ganti Password</span>
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="rounded-md py-2 px-2.5 cursor-pointer text-destructive focus:text-destructive"
                                @select="logout"
                            >
                                <LogOut class="size-4" />
                                <span>Logout</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger
                            class="inline-flex lg:hidden h-8 w-8 items-center justify-center rounded-md text-white/90 hover:bg-white/10 hover:text-white transition-colors"
                            aria-label="Buka menu"
                        >
                            <MenuIcon class="size-5" />
                        </SheetTrigger>
                        <SheetContent side="right" class="w-80 p-0">
                            <SheetHeader
                                class="border-b border-border px-5 py-4"
                            >
                                <SheetTitle>{{ companyName }}</SheetTitle>
                                <SheetDescription class="text-xs">
                                    {{ user?.name }} ·
                                    <span class="capitalize">{{
                                        user?.role
                                    }}</span>
                                </SheetDescription>
                            </SheetHeader>
                            <nav
                                class="flex flex-col gap-0.5 px-3 py-4 overflow-y-auto"
                            >
                                <template
                                    v-for="item in menuTree"
                                    :key="`m-${item.code}`"
                                >
                                    <Link
                                        v-if="!item.children?.length"
                                        :href="routeOrNull(item.route) || '#'"
                                        :class="[
                                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                            isActive(item.route)
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-foreground hover:bg-muted',
                                        ]"
                                        @click="mobileMenuOpen = false"
                                    >
                                        {{ item.label }}
                                    </Link>
                                    <div v-else class="pt-3 first:pt-0">
                                        <p
                                            class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                        >
                                            {{ item.label }}
                                        </p>
                                        <Link
                                            v-for="child in item.children"
                                            :key="`m-${child.code}`"
                                            :href="
                                                routeOrNull(child.route) || '#'
                                            "
                                            :class="[
                                                'rounded-md px-3 py-2 text-sm transition-colors block',
                                                isActive(child.route)
                                                    ? 'bg-primary/10 text-primary font-medium'
                                                    : 'text-foreground hover:bg-muted',
                                            ]"
                                            @click="mobileMenuOpen = false"
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </div>
                                </template>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <!-- Main content (max-width centered, responsive padding) -->
        <main
            class="mx-auto w-full max-w-screen-2xl px-3 py-4 sm:px-4 sm:py-5 lg:px-6 lg:py-6"
        >
            <Transition name="page" mode="out-in" appear>
                <div :key="currentComponent">
                    <slot />
                </div>
            </Transition>
        </main>

        <footer
            class="mx-auto w-full max-w-screen-2xl px-3 py-4 sm:px-4 lg:px-6 text-center"
        >
            <p class="text-[11px] text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ companyName }} — Sistem
                Distribusi & Pergudangan
            </p>
        </footer>

        <Toaster
            position="top-right"
            :rich-colors="true"
            :close-button="true"
            theme="light"
        />
    </div>
</template>
