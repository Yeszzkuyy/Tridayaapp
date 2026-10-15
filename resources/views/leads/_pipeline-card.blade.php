{{-- Kartu lead pipeline. $cardDate: tanggal yang ditampilkan (assign/incoming). --}}
<div class="kanban-card bg-white dark:bg-slate-700 rounded-xl border border-slate-200 dark:border-slate-600 p-3 shadow-sm hover:shadow transition"
     data-lead-id="{{ $lead->id }}" data-mine="{{ (int) $lead->assigned_to === (int) auth()->id() ? 1 : 0 }}">
    <div class="flex items-center justify-between mb-1">
        @if($lead->pt_group)
            <span class="inline-flex px-1.5 py-0.5 rounded {{ \App\Models\Lead::PT_COLORS[$lead->pt_group] ?? 'bg-indigo-50 text-indigo-700' }} text-[11px] font-semibold">
                {{ $lead->pt_group }}
            </span>
        @endif
        <span class="text-[11px] text-slate-400">{{ $cardDate?->format('d M y') }}</span>
    </div>
    <a href="{{ route('leads.show', $lead) }}"
       class="block font-semibold text-slate-800 hover:text-accent-600 leading-snug">
        {{ $lead->customer->name ?? 'N/A' }}
    </a>
    <p class="text-xs text-slate-500 mt-1 line-clamp-2">
        {{ $lead->kebutuhan ? Str::limit($lead->kebutuhan, 60) : '-' }}
    </p>
    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100 dark:border-slate-600">
        <span class="text-[11px] text-slate-500">
            {{ $lead->segment ? \App\Http\Controllers\LeadController::label($lead->segment) : '' }}
        </span>
        <span class="text-[11px] font-medium text-slate-600">
            {{ $lead->assignee?->name }}
        </span>
    </div>
</div>
