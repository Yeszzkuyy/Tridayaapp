<x-app-layout>
    @include('partials.live-feed')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">{{ __('Activity Log Management') }}</h1>
            <p class="text-slate-500 mt-1">
                {{ __('Management user activity history (assign & manage leads)') }}
                @if($filterUser)
                    {{ __('— filter:') }} <span class="font-semibold text-accent-600">{{ $filterUser->name }}</span>
                    <a href="{{ route('manage-sales.activity-log') }}" class="text-slate-400 hover:text-red-500 ml-1" title="{{ __('Clear filter') }}">&times;</a>
                @endif
            </p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('manage-sales.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <div class="p-4 mb-4 rounded-xl bg-slate-50 border border-slate-200">
            <form method="GET" action="{{ route('manage-sales.activity-log') }}" class="flex flex-wrap gap-4">
                <div>
                    <label for="user" class="sr-only">{{ __('User') }}</label>
                    <select id="user" name="user" class="px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-accent-500 focus:border-transparent">
                        <option value="">{{ __('All Users') }}</option>
                        @foreach($managementUsers as $user)
                            <option value="{{ $user->id }}" {{ request('user') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date_from" class="sr-only">{{ __('From Date') }}</label>
                    <x-datepicker id="date_from" name="date_from" value="{{ request('date_from') }}"></x-datepicker>
                </div>
                <div>
                    <label for="date_to" class="sr-only">{{ __('To Date') }}</label>
                    <x-datepicker id="date_to" name="date_to" value="{{ request('date_to') }}"></x-datepicker>
                </div>
                <x-icon-button icon="filter" type="submit" title="{{ __('Filter') }}" />
                <x-icon-button as="a" icon="reset" href="{{ route('manage-sales.activity-log') }}" title="{{ __('Reset') }}" />
            </form>
        </div>


        <div x-data="liveFeed(@js(route('manage-sales.activity-log.feed', request()->query())), 60000)">
            @include('manage-sales.partials.activity-log-body')
        </div>
    </div>
</x-app-layout>