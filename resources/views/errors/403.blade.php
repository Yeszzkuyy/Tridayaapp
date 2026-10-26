{{-- Halaman error 403 — konsisten dengan layout auth (x-guest-layout) + komponen
     bersama x-icon-button. Dirender untuk penolakan akses HTTP 403 di seluruh
     aplikasi. Jangan ubah status respons: backend tetap menolak dengan 403. --}}
<x-guest-layout>
    <div class="flex flex-col items-center py-4 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 text-red-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        </div>

        <h1 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Access Denied') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('You do not have permission to access this page.') }}</p>

        <div class="mt-6 flex items-center gap-3">
            <x-icon-button as="a" icon="back" href="{{ url()->previous() }}"
                           label="{{ __('Back') }}" title="{{ __('Back') }}" />
            <x-icon-button as="a" href="{{ route('dashboard') }}"
                           label="{{ __('Dashboard') }}" title="{{ __('Dashboard') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
            </x-icon-button>
        </div>
    </div>
</x-guest-layout>
