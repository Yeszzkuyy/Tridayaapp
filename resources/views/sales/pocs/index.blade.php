<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">POC/Demo</h1>
            <p class="text-slate-500 mt-1">{{ __('Jadwal proof of concept dan demo produk.') }}</p>
        </div>
        <x-icon-button as="a" icon="add" href="{{ route('sales.pocs.create') }}" title="Add POC/Demo" />
    </div>

    <form method="GET" action="{{ route('sales.pocs.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="w-full sm:w-auto sm:flex-1 sm:min-w-48 sm:max-w-xs">
            <label for="pocs-search" class="block text-xs font-medium text-slate-500 mb-1">{{ __('Cari customer') }}</label>
            <input id="pocs-search" type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Cari customer...') }}"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-accent-500">
        </div>
        <div>
            <label for="pocs-type" class="block text-xs font-medium text-slate-500 mb-1">{{ __('Tipe') }}</label>
            <select id="pocs-type" name="type" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-accent-500">
                <option value="">{{ __('Semua Tipe') }}</option>
                @foreach(\App\Models\Poc::TYPES as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ \App\Models\Poc::typeLabel($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="pocs-status" class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select id="pocs-status" name="status" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-accent-500">
                <option value="">{{ __('Semua Status') }}</option>
                @foreach(\App\Models\Poc::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\Poc::statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <x-icon-button icon="filter" type="submit" title="Filter" />
            @if(request('search') || request('type') || request('status'))
                <x-icon-button as="a" icon="reset" href="{{ route('sales.pocs.index') }}" title="Reset" />
            @endif
        </div>
    </form>

    @include('sales.pocs._table')
</x-app-layout>
