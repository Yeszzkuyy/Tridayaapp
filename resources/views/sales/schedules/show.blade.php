<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $schedule->title }}</h1>
                <p class="text-slate-500 mt-1">{{ \App\Models\SalesSchedule::typeLabel($schedule->type) }} • {{ \App\Models\SalesSchedule::statusLabel($schedule->status) }}</p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ route('leads.show', $schedule->lead) }}" title="{{ __('Back') }}" />
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <p class="text-slate-500">{{ __('Lead') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $schedule->lead->customer?->name ?? 'Lead #'.$schedule->lead_id }}</span></p>
                <p class="text-slate-500">{{ __('PIC') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $schedule->assignee?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Mulai') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $schedule->start_at?->format('d M Y H:i') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Selesai') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $schedule->end_at?->format('d M Y H:i') ?? '-' }}</span></p>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-slate-500 shrink-0">{{ __('Lokasi') }}:</span>
                    <x-location-link :location="$schedule->location" preview class="font-medium min-w-0 flex-1" />
                </div>
                <p class="text-slate-500">{{ __('Dibuat oleh') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $schedule->creator?->name ?? '-' }}</span></p>
            </div>
            @if($schedule->description)
                <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $schedule->description }}</p>
            @endif

            @if(!in_array($schedule->status, ['completed', 'cancelled']))
                @php
                    $canManage = in_array(auth()->id(), [$schedule->assigned_to, $schedule->created_by]) || auth()->user()?->can('manage-sales-leads');
                @endphp
                @if($canManage)
                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-icon-button as="a" icon="edit" href="{{ route('sales.schedules.edit', $schedule) }}" title="{{ __('Edit') }}" />
                        <form action="{{ route('sales.schedules.complete', $schedule) }}" method="POST" class="inline">
                            @csrf
                            <x-icon-button type="submit" label="{{ __('Complete') }}" title="{{ __('Complete') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </x-icon-button>
                        </form>
                        <form action="{{ route('sales.schedules.cancel', $schedule) }}" method="POST" class="inline"
                              onsubmit="return confirm('{{ __('Batalkan jadwal ini?') }}')">
                            @csrf
                            <x-icon-button icon="delete" type="submit" title="{{ __('Cancel') }}" />
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
