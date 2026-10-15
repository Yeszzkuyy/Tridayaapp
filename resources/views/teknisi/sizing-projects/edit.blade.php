<x-app-layout>
@php
    $statuses = [
        'draft' => 'Draft',
        'in_progress' => 'In Progress',
        'waiting_approval' => 'Waiting Approval',
        'completed' => 'Completed',
    ];
@endphp

<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Edit Sizing Project') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ $sizingProject->project->project_name ?? 'Sizing Project' }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('teknisi.sizing-projects.show', $sizingProject) }}" title="{{ __('Kembali') }}" />
    </div>

    @if ($errors->any())
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6">
        <form method="POST" action="{{ route('teknisi.sizing-projects.update', $sizingProject) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="project_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Project <span class="text-red-500">*</span></label>
                    <select name="project_id" id="project_id" required
                            class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                        <option value="">{{ __('— Pilih Project —') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}"
                                @selected(old('project_id', $sizingProject->project_id) == $project->id)>
                                {{ $project->project_name }}
                                @if ($project->customer) — {{ $project->customer->name }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="sales_pic" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sales PIC</label>
                    <input type="text" name="sales_pic" id="sales_pic" value="{{ old('sales_pic', $sizingProject->sales_pic) }}" maxlength="255"
                           class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                </div>
            </div>

            <div>
                <label for="customer_needs" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Customer Needs</label>
                <textarea name="customer_needs" id="customer_needs" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('customer_needs', $sizingProject->customer_needs) }}</textarea>
            </div>

            <div>
                <label for="recommendation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Rekomendasi') }}</label>
                <textarea name="recommendation" id="recommendation" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('recommendation', $sizingProject->recommendation) }}</textarea>
            </div>

            <div>
                <label for="specifications" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Spesifikasi') }}</label>
                <textarea name="specifications" id="specifications" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('specifications', $sizingProject->specifications) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="quantity" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Quantity</label>
                    <input type="number" name="quantity" id="quantity" value="{{ old('quantity', $sizingProject->quantity) }}" min="0"
                           class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select name="status" id="status" required
                            class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $sizingProject->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="topology" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Topologi') }}</label>
                <textarea name="topology" id="topology" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('topology', $sizingProject->topology) }}</textarea>
            </div>

            <div>
                <label for="technical_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Catatan Teknis') }}</label>
                <textarea name="technical_notes" id="technical_notes" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('technical_notes', $sizingProject->technical_notes) }}</textarea>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="2"
                          class="mt-1 block w-full rounded-xl border-slate-300 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-100 text-sm focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $sizingProject->notes) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('teknisi.sizing-projects.show', $sizingProject) }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Simpan Perubahan') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>