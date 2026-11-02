@props(['proposal'])

@php
    $stage = $proposal->handoffStage();

    if ($stage !== null) {
        $stages = \App\Models\Proposal::handoffStages();
        $keys = array_keys($stages);
        $currentIndex = array_search($stage, $keys, true);
    }
@endphp

@if($stage === null)
    <span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ' . \App\Models\Proposal::statusBadgeClasses($proposal->status)]) }}>
        {{ \App\Models\Proposal::statusLabel($proposal->status) }}
    </span>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
        @foreach($stages as $key => $label)
            @php
                $index = array_search($key, $keys, true);
                $isCurrent = $index === $currentIndex;
                $isDone = $index < $currentIndex;
            @endphp
            <span @class([
                'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold',
                'bg-accent-600 text-white' => $isCurrent,
                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $isDone,
                'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300' => ! $isCurrent && ! $isDone,
            ])>
                {{ $label }}
            </span>
        @endforeach
    </div>
@endif
