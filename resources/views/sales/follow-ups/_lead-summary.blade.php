{{-- Ringkasan lead untuk form follow-up dari My Leads. Customer & lead
     sudah terkunci lewat hidden input, jadi sales tinggal mengisi catatan. --}}
<div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Lead') }}</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-800 dark:text-slate-100">#{{ $summary['id'] ?? '-' }}</p>
        </div>

        <div class="flex items-center gap-2">
            @if(!empty($summary['stage']))
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $summary['stage_classes'] ?? 'bg-slate-100 text-slate-600' }}">{{ $summary['stage'] }}</span>
            @endif
            @if(!empty($summary['url']))
                <x-icon-button as="a" icon="view" :href="$summary['url']" title="{{ __('View Lead') }}" />
            @endif
        </div>
    </div>

    <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
        @if(!empty($summary['pic']))
            <div>
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('PIC') }}</dt>
                <dd class="font-medium text-slate-700 dark:text-slate-200">
                    {{ $summary['pic'] }}
                    @if(!empty($summary['pic_phone']))
                        <span class="font-normal text-slate-500 dark:text-slate-400">({{ $summary['pic_phone'] }})</span>
                    @endif
                </dd>
            </div>
        @endif

        @if(!empty($summary['project']))
            <div>
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('Project') }}</dt>
                <dd class="font-medium text-slate-700 dark:text-slate-200">{{ $summary['project'] }}</dd>
            </div>
        @endif

        @if(!empty($summary['need']))
            <div class="sm:col-span-2">
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('Need') }}</dt>
                <dd class="text-slate-700 dark:text-slate-200">{{ $summary['need'] }}</dd>
            </div>
        @endif
    </dl>
</div>
