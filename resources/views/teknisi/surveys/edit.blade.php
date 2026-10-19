<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ __('Edit Survey') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('Edit survey data — ') }}<span class="font-semibold text-slate-700">{{ $survey->title ?? '-' }}</span></p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('teknisi.surveys.index') }}" title="{{ __('Back') }}" />
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 w-full">
        <form action="{{ route('teknisi.surveys.update', $survey) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                {{-- Judul + Sales --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Survey Title') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $survey->title) }}" required maxlength="255"
                               placeholder="{{ __('Example: Building A CCTV Needs Survey') }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Sales (pemilik survey — yang bisa memantau hasilnya) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Sales') }} <span class="text-red-500">*</span></label>
                        <select name="sales_user_id" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('Select Sales') }}</option>
                            @foreach($salesUsers as $sales)
                                <option value="{{ $sales->id }}" {{ old('sales_user_id', $survey->sales_user_id) == $sales->id ? 'selected' : '' }}>{{ $sales->name }}</option>
                            @endforeach
                        </select>
                        @error('sales_user_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Customer + Project (opsional) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Customer') }}</label>
                        <select name="customer_id"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('No Customer') }}</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id', $survey->customer_id) == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        @error('customer_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Project') }}</label>
                        <select name="project_id"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="">{{ __('No project yet') }}</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id', $survey->project_id) == $project->id ? 'selected' : '' }}>{{ $project->project_name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Optional — select if the project already exists.') }}</p>
                        @error('project_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Sales Request --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Sales Request') }}</label>
                    <textarea name="sales_request" rows="3"
                              class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">{{ old('sales_request', $survey->sales_request) }}</textarea>
                    @error('sales_request') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Survey Date --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Survey Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="survey_date" value="{{ old('survey_date', $survey->survey_date) }}" required
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('survey_date') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                        <select name="status" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                            <option value="draft" {{ old('status', $survey->status) === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                            <option value="scheduled" {{ old('status', $survey->status) === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                            <option value="on_survey" {{ old('status', $survey->status) === 'on_survey' ? 'selected' : '' }}>{{ __('On Survey') }}</option>
                            <option value="completed" {{ old('status', $survey->status) === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                        </select>
                        @error('status') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Location --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Location') }}</label>
                        <input type="text" name="location" value="{{ old('location', $survey->location) }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('location') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- PIC --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('PIC') }}</label>
                        <input type="text" name="pic" value="{{ old('pic', $survey->pic) }}"
                               class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">
                        @error('pic') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Survey Data --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Survey Data') }}</label>
                    <textarea name="survey_data" rows="4"
                              class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">{{ old('survey_data', $survey->survey_data) }}</textarea>
                    @error('survey_data') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Survey Report --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Survey Report') }}</label>
                    <textarea name="survey_report" rows="4"
                              class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">{{ old('survey_report', $survey->survey_report) }}</textarea>
                    @error('survey_report') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="3"
                              class="w-full rounded-xl border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 focus:border-accent-500 focus:ring-accent-500">{{ old('notes', $survey->notes) }}</textarea>
                    @error('notes') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                    {{ __('Save Changes') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
                <a href="{{ route('teknisi.surveys.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Cancel') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
