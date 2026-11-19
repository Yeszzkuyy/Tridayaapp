<x-app-layout>
    <div x-data="{ addOpen: false }">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('DAR') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Daily Activity Report') }}</p>
            </div>
            @can('manage-sales')
                <x-flow-button as="button" type="button" x-on:click="addOpen = true"
                               icon="add"
                               label="{{ __('Add Activity') }}"
                               title="{{ __('Add Activity') }}" />
            @endcan
        </div>

        <div class="bg-[var(--card-bg)] dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-4 mb-4">
            <form method="GET" action="{{ route('sales.dar.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <label for="dar-date" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Date') }}</label>
                    <input id="dar-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label for="dar-from" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Date from') }}</label>
                    <input id="dar-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label for="dar-to" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Date to') }}</label>
                    <input id="dar-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                @if($canViewAll)
                    <div>
                        <label for="dar-sales" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Sales') }}</label>
                        <select id="dar-sales" name="user_id"
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <option value="">{{ __('All Sales') }}</option>
                            @foreach($salesUsers as $sales)
                                <option value="{{ $sales->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $sales->id)>{{ $sales->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="dar-customer" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Customer') }}</label>
                    <input id="dar-customer" type="text" name="customer" value="{{ $filters['customer'] ?? '' }}" placeholder="{{ __('Customer name...') }}"
                           class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                </div>
                <div>
                    <label for="dar-type" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Activity Type') }}</label>
                    <select id="dar-type" name="type"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <option value="">{{ __('All Types') }}</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ \App\Models\FollowUp::typeLabel($type) }}</option>
                        @endforeach
                        <option value="pipeline" @selected(($filters['type'] ?? '') === 'pipeline')>{{ __('Pipeline Stage') }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                    <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                    @if(collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty())
                        <x-icon-button as="a" icon="reset" href="{{ route('sales.dar.index') }}" title="{{ __('Reset') }}" />
                    @endif
                </div>
            </form>
        </div>

        <div class="bg-[var(--card-bg)] dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-slate-200 dark:divide-slate-600">
                    <thead class="bg-slate-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Time') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Sales') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Customer') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Project') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Activity Type') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Description / Outcome') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Related Lead') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-200">{{ __('Source') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-600">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                <td class="px-4 py-3 whitespace-nowrap text-xs font-medium text-slate-700 dark:text-slate-200">{{ $activity->date }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $activity->time }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">{{ $activity->user?->name ?? __('System') }}</td>
                                <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">
                                    <div class="min-w-[150px] max-w-[220px] truncate" title="{{ $activity->customer }}">{{ $activity->customer }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                    <div class="min-w-[120px] max-w-[180px] truncate" title="{{ $activity->project }}">{{ $activity->project ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $activity->activity_type }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                    <div class="min-w-[200px] max-w-[320px]">
                                        <p class="line-clamp-3" title="{{ $activity->description }}">{{ $activity->description ?: '-' }}</p>
                                        @if($activity->outcome)
                                            <p class="mt-1 text-[11px] font-medium text-green-700 dark:text-green-400">{{ __('Outcome') }}: {{ \Illuminate\Support\Str::limit($activity->outcome, 120) }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs">
                                    @if($activity->lead_id)
                                        <a href="{{ route('leads.show', $activity->lead_id) }}" class="font-medium text-accent-600 hover:underline dark:text-accent-400">#{{ $activity->lead_id }}</a>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $activity->source }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-14 text-center">
                                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300">
                                        <x-icon name="activity" class="h-6 w-6" />
                                    </span>
                                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-slate-300">{{ __('No activity in this filter.') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Completed follow ups and stage changes appear here automatically.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($activities->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-600">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>

        @can('manage-sales')
            <div x-show="addOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ __('Add Activity') }}">
                <div class="absolute inset-0 bg-slate-900/60" @click="addOpen = false"></div>
                <div class="relative w-full max-w-xl rounded-2xl border border-slate-200 bg-[var(--card-bg)] p-5 shadow-2xl dark:border-slate-600 dark:bg-slate-800">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('Add Activity') }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('For meetings, visits, or calls done outside the app.') }}</p>
                        </div>
                        <button type="button" @click="addOpen = false" class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 dark:hover:bg-slate-700" title="{{ __('Close') }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span class="sr-only">{{ __('Close') }}</span>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('sales.dar.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="dar-lead" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Customer / Lead') }} <span class="text-red-500">*</span></label>
                            <select id="dar-lead" name="lead_id" required
                                    class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                <option value="">{{ __('Choose lead') }}</option>
                                @foreach($leadOptions as $lead)
                                    <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>
                                        {{ $lead->customer?->name ?? ('Lead #'.$lead->id) }} - {{ $lead->project_name ?: \App\Models\Lead::salesStatusLabel($lead->status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('lead_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="dar-type-input" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Activity Type') }}</label>
                                <select id="dar-type-input" name="type" required
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ \App\Models\FollowUp::typeLabel($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="dar-activity-date" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Date') }}</label>
                                <input id="dar-activity-date" type="date" name="activity_date" required max="{{ date('Y-m-d') }}" value="{{ old('activity_date', date('Y-m-d')) }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <label for="dar-activity-time" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Time (optional)') }}</label>
                                <input id="dar-activity-time" type="time" name="activity_time" value="{{ old('activity_time') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <label for="dar-outcome" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Outcome (optional)') }}</label>
                                <input id="dar-outcome" type="text" name="outcome" value="{{ old('outcome') }}" maxlength="2000"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                        </div>

                        <div>
                            <label for="dar-description" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Description') }} <span class="text-red-500">*</span></label>
                            <textarea id="dar-description" name="description" rows="3" required
                                      placeholder="{{ __('What was discussed or agreed?') }}"
                                      class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="dar-next-date" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Next Follow Up (optional)') }}</label>
                                <input id="dar-next-date" type="date" name="next_follow_up_date" min="{{ date('Y-m-d') }}" value="{{ old('next_follow_up_date') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div>
                                <label for="dar-next-time" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Time (optional)') }}</label>
                                <input id="dar-next-time" type="time" name="next_follow_up_time" value="{{ old('next_follow_up_time') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit"
                                    class="group relative inline-flex h-10 items-center overflow-hidden rounded-xl bg-accent-600 px-6 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                {{ __('Save') }}
                                <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                            </button>
                            <button type="button" @click="addOpen = false"
                                    class="inline-flex h-10 items-center rounded-xl border border-slate-200 px-6 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
