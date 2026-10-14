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
                        <a href="{{ route('sales.schedules.edit', $schedule) }}"
                           class="px-4 py-2 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm font-medium transition">{{ __('Edit') }}</a>
                        <form action="{{ route('sales.schedules.complete', $schedule) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium transition">{{ __('Complete') }}</button>
                        </form>
                        <form action="{{ route('sales.schedules.cancel', $schedule) }}" method="POST" class="inline"
                              onsubmit="return confirm('{{ __('Batalkan jadwal ini?') }}')">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium transition">{{ __('Cancel') }}</button>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
