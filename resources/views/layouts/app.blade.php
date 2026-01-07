<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Forms') - {{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS CDN (for standalone mode) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Alpine.js (included with Livewire) -->
    @livewireStyles
</head>
<body class="font-sans antialiased bg-base-200">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="navbar bg-base-100 border-b border-base-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="flex justify-between w-full">
                    <div class="flex items-center gap-8">
                        <!-- Logo -->
                        <a href="{{ route('forms.index') }}" class="text-xl font-bold">
                            Forms
                        </a>

                        <!-- Navigation Links -->
                        <div class="hidden sm:flex">
                            <x-artisanpack-menu horizontal>
                                <x-artisanpack-menu-item
                                    link="{{ route('forms.index') }}"
                                    label="All Forms"
                                    :active="request()->routeIs('forms.index')"
                                />
                            </x-artisanpack-menu>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <x-artisanpack-button
                            link="{{ route('forms.create') }}"
                            label="Create Form"
                            icon="o-plus"
                            color="primary"
                            class="btn-sm"
                        />
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        @hasSection('header')
            <header class="bg-base-100 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    @yield('header')
                </div>
            </header>
        @endif

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                <x-artisanpack-alert
                    type="success"
                    :message="session('success')"
                    dismissible
                />
            </div>
        @endif

        @if (session('error'))
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                <x-artisanpack-alert
                    type="error"
                    :message="session('error')"
                    dismissible
                />
            </div>
        @endif

        <!-- Page Content -->
        <main class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
