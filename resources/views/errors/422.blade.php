{{-- Halaman error 422 — permintaan dipahami tetapi ditolak aturan aplikasi
     (mis. status dokumen belum siap, target assign bukan role yang sesuai).
     Bukan masalah akses: itu 403. Pesan asli dari abort(422, '...') ditampilkan
     agar user tahu alasannya, bukan template mentah Symfony. Jangan ubah
     status respons: backend tetap menolak dengan 422. --}}
@php
    $detail = trim((string) ($exception?->getMessage() ?? ''));
    $generic = ['', 'Unprocessable Content', 'Unprocessable Entity', 'HTTP 422', '422'];
    $detail = in_array($detail, $generic, true) ? null : $detail;
@endphp
<x-guest-layout>
    <div class="flex flex-col items-center py-4 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94 2.7 17.2A2.25 2.25 0 0 0 4.65 20.5h14.7a2.25 2.25 0 0 0 1.95-3.3L13.66 3.94a1.9 1.9 0 0 0-3.32 0Z" />
            </svg>
        </div>

        <h1 class="mt-4 text-xl font-semibold text-gray-900">{{ __('Request Rejected') }}</h1>
        <p class="mt-2 max-w-md text-sm text-gray-600">{{ $detail ?? __('This request could not be processed.') }}</p>
        <p class="mt-1 text-xs text-gray-400">{{ __('Your access is fine — the data or status does not allow this action.') }}</p>

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
