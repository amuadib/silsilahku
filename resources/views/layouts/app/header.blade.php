<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                @if(Auth::user()->isAdmin())
                <flux:navbar.item icon="clipboard-document-list" :href="route('activity-log.index')" :current="request()->routeIs('activity-log.index')" wire:navigate>
                    {{ __('Activity Log') }}
                </flux:navbar.item>
                @endif
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                {{-- <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>

                <flux:separator vertical variant="subtle" class="my-2" /> --}}

                <div x-data="{ 
                    appearance: localStorage.getItem('appearance') || 'system',
                    dark: false,
                    init() {
                        this.dark = this.appearance === 'dark' || (this.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                        this.update();
                    },
                    toggle() {
                        this.appearance = this.dark ? 'light' : 'dark';
                        this.dark = !this.dark;
                        localStorage.setItem('appearance', this.appearance);
                        this.update();
                    },
                    update() {
                        if (this.dark) document.documentElement.classList.add('dark');
                        else document.documentElement.classList.remove('dark');
                    }
                }" class="flex items-center">
                    <flux:navbar.item x-show="!dark" icon="moon" x-on:click="toggle" variant="subtle" class="!h-10 [&>div>svg]:size-5 cursor-pointer" />
                    <flux:navbar.item x-show="dark" icon="sun" x-on:click="toggle" variant="subtle" class="!h-10 [&>div>svg]:size-5 cursor-pointer" />
                </div>
            </flux:navbar>
@auth
            <x-desktop-user-menu />
@endauth
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    @if(Auth::user()->isAdmin())
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('activity-log.index')" :current="request()->routeIs('activity-log.index')" wire:navigate>
                        {{ __('Activity Log')  }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        <!-- Custom Toast Notifications -->
        <div x-data="{ 
                notifications: [], 
                add(n) { 
                    let id = Date.now();
                    this.notifications.push({ id, ...n }); 
                    setTimeout(() => { this.notifications = this.notifications.filter(i => i.id !== id) }, 5000); 
                } 
            }" 
            x-init="
                @if(session('success')) add({ message: '{{ session('success') }}', type: 'success' }); @endif
                @if(session('error')) add({ message: '{{ session('error') }}', type: 'error' }); @endif
            "
            @notify.window="add($event.detail)"
            class="fixed bottom-6 right-6 z-[99] flex flex-col gap-2 pointer-events-none">
            <template x-for="n in notifications" :key="n.id">
                <div x-show="true" 
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="pointer-events-auto bg-zinc-900 border border-zinc-700 text-white px-4 py-3 rounded-lg shadow-xl flex items-center gap-3 min-w-[280px]"
                    :class="n.type === 'error' ? 'border-red-500/50' : 'border-zinc-700'">
                    <div x-show="n.type === 'success' || !n.type" class="text-green-400">
                        <flux:icon name="check-circle" variant="solid" class="size-5" />
                    </div>
                    <div x-show="n.type === 'error'" class="text-red-400">
                        <flux:icon name="x-circle" variant="solid" class="size-5" />
                    </div>
                    <span x-text="n.message" class="text-sm font-medium"></span>
                </div>
            </template>
        </div>

        @fluxScripts
    </body>
</html>
