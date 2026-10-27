<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Lead Activity Log') }}</h1>
            <p class="text-slate-500 mt-1">
                {{ __('History of all lead changes and the users who made them') }}
                @if($filterUser)
                    {{ __('— filter:') }} <span class="font-semibold text-accent-600">{{ $filterUser->name }}</span>
                    <a href="{{ route('leads.activities') }}" class="text-slate-400 hover:text-red-500 ml-1" title="{{ __('Clear filter') }}">&times;</a>
                @endif
            </p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        @php
            $logColor = function (?int $customerId): array {
                if (! $customerId) {
                    return ['hsl(220, 10%, 90%)', 'hsl(220, 10%, 35%)'];
                }
                $hue = fmod($customerId * 137.5, 360);

                return ["hsl({$hue}, 70%, 90%)", "hsl({$hue}, 70%, 30%)"];
            };

            $formatLogValue = function (string $field, $value) use ($customerNames, $userNames): string {
                if ($value === null || $value === '') {
                    return '-';
                }
                if ($field === 'customer_id') {
                    return (string) ($customerNames[$value] ?? $value);
                }
                if ($field === 'assigned_to') {
                    return (string) ($userNames[$value] ?? $value);
                }
                if (in_array($field, ['pt_group', 'segment', 'source', 'status'])) {
                    return \App\Http\Controllers\LeadController::label((string) $value);
                }
                if ($field === 'incoming_date') {
                    return \Carbon\Carbon::parse($value)->translatedFormat('d M Y');
                }

                return (string) $value;
            };
        @endphp

        @if($activities->isEmpty())
            <div class="text-center py-12 px-4">
                <p class="text-sm font-medium text-slate-500">{{ __('No lead activity yet.') }}</p>
            </div>
        @else
            <ol class="relative border-l-2 border-slate-200 ml-3 space-y-6">
                @foreach($activities as $activity)
                    @php [$dotBg] = ($logColor)($activity->lead?->customer_id); @endphp
                    <li class="ml-6">
                        <span class="absolute -left-[9px] flex w-4 h-4 rounded-full" style="background: {{ $dotBg }}"></span>

                        <div class="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm">
                            <x-user-avatar :user="$activity->user" size="w-7 h-7" text="text-xs" />
                            <span class="font-semibold text-slate-800">{{ $activity->user ? ($userNames[$activity->user_id] ?? $activity->user->name) : __('System') }}</span>
                            <span class="text-slate-600">{{ $activity->actionLabel() }}</span>
                            @if($activity->lead && $activity->lead->customer)
                                @php [$badgeBg, $badgeText] = ($logColor)($activity->lead->customer_id); @endphp
                                — <a href="{{ route('leads.show', $activity->lead) }}"
                                     class="font-medium rounded-full px-2.5 py-0.5 hover:opacity-80 transition"
                                     style="background: {{ $badgeBg }}; color: {{ $badgeText }}">
                                    {{ $activity->lead->customer->name }}
                                </a>
                            @else
                                — <span class="text-slate-400 italic">{{ __('lead has been permanently deleted') }}</span>
                            @endif
                        </div>

                        @if($activity->changes)
                            <div class="mt-2 space-y-1">
                                @foreach($activity->changes as $field => $change)
                                    @php
                                        $change = is_array($change) ? $change : ['old' => null, 'new' => $change];
                                        $hasOld = ($change['old'] ?? null) !== null && ($change['old'] ?? null) !== '';
                                        $old = ($formatLogValue)($field, $change['old'] ?? null);
                                        $new = ($formatLogValue)($field, $change['new'] ?? null);
                                    @endphp
                                    <div class="text-xs bg-slate-50 dark:bg-slate-800 rounded-lg px-3 py-2 inline-block mr-2">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">
                                            {{ __(\App\Models\LeadActivity::FIELD_LABELS[$field] ?? $field) }}:
                                        </span>
                                        @if($hasOld)
                                            <span class="text-red-500 line-through mr-1">{{ $old }}</span>
                                        @endif
                                        <span class="text-green-600 dark:text-green-400">{{ $new }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <p class="text-xs text-slate-400 mt-1">{{ $activity->created_at->format('d M Y, H:i') }} WIB</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-6">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
