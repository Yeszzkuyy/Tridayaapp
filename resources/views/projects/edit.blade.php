
<x-app-layout>
<div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Edit Project</h1>
            <p class="text-slate-500 mt-1">Edit: {{ $project->project_name }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('projects.show', $project) }}" title="Kembali" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 w-full">
        <form action="{{ route('projects.update', $project) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Project</label>
                    <input type="text" name="project_name" value="{{ old('project_name', $project->project_name) }}" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ $project->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Jenis Pekerjaan</label>
                    <select name="work_type_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @foreach($workTypes as $workType)
                            <option value="{{ $workType->id }}" {{ $project->work_type_id == $workType->id ? 'selected' : '' }}>{{ $workType->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Manager</label>
                    <select name="account_manager_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">Pilih</option>
                        @foreach($accountManagers as $am)
                            <option value="{{ $am->id }}" {{ $project->account_manager_id == $am->id ? 'selected' : '' }}>{{ $am->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- PIC Engineer --}}
                @php
                    $selectedPic = old('pic_engineer', $project->pic_engineer);
                    $selectedSupport = collect((array) old('support_technicians', $project->support_technicians))
                        ->flatMap(fn ($v) => explode(',', (string) $v))
                        ->map(fn ($v) => trim($v))
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        PIC Engineer <span class="text-red-500">*</span>
                    </label>
                    <select name="pic_engineer" required
                            class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <option value="">- Pilih Teknisi -</option>
                        @foreach($technicians as $tech)
                            <option value="{{ $tech->name }}" {{ $selectedPic === $tech->name ? 'selected' : '' }}>{{ $tech->name }}</option>
                        @endforeach
                        @if($selectedPic && !$technicians->pluck('name')->contains($selectedPic))
                            <option value="{{ $selectedPic }}" selected>{{ $selectedPic }} (data lama)</option>
                        @endif
                    </select>
                    @error('pic_engineer') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Support Technicians --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Support Technicians <span class="text-slate-400 text-xs">(Opsional)</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($technicians as $tech)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="support_technicians[]" value="{{ $tech->name }}"
                                       class="rounded border-slate-300 text-accent-600 focus:ring-accent-500"
                                       {{ $selectedSupport->contains($tech->name) ? 'checked' : '' }}>
                                {{ $tech->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Centang teknisi pendukung (boleh lebih dari satu).</p>
                    @error('support_technicians') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="project_status_id" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" {{ (old('project_status_id', $project->project_status_id) == $status->id) ? 'selected' : '' }}>
                                {{ $status->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Progress --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Progress (0-100%)
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="range" 
                               name="progress" 
                               min="0" 
                               max="100" 
                               value="{{ old('progress', $project->progress ?? 0) }}"
                               oninput="document.getElementById('progressValue').textContent = this.value + '%'"
                               class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
                        <span id="progressValue" class="text-sm font-semibold text-slate-700 min-w-[50px]">
                            {{ $project->progress ?? 0 }}%
                        </span>
                    </div>
                    @error('progress') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                    <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">{{ $project->description }}</textarea>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium transition">Update</button>
                <a href="{{ route('projects.show', $project) }}" class="px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white font-medium transition">Batal</a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
