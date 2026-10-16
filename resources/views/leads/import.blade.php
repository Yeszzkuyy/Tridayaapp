<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Import Lead / Opportunity</h1>
            <p class="text-slate-500 mt-1">{{ __('Import data dari file Excel (.xlsx) atau CSV') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="Kembali" />
    </div>

    {{-- Hasil Import --}}
    @if(session('import_results'))
        @php $results = session('import_results'); @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4">{{ __('Hasil Import') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="p-4 bg-green-50 rounded-xl border border-green-200">
                    <p class="text-sm text-green-600 font-medium">{{ __('Berhasil') }}</p>
                    <p class="text-2xl font-bold text-green-700">{{ $results['success'] }}</p>
                </div>
                <div class="p-4 bg-red-50 rounded-xl border border-red-200">
                    <p class="text-sm text-red-600 font-medium">{{ __('Gagal') }}</p>
                    <p class="text-2xl font-bold text-red-700">{{ $results['failed'] }}</p>
                </div>
            </div>
            @if(!empty($results['errors']))
                <div class="mt-4">
                    <p class="text-sm font-medium text-slate-700 mb-2">Detail Error:</p>
                    <div class="max-h-48 overflow-y-auto space-y-1">
                        @foreach($results['errors'] as $error)
                            <p class="text-sm text-red-600 bg-red-50 px-3 py-1.5 rounded-lg">{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Error --}}
    @error('file')
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-sm text-red-600">{{ $message }}</p>
        </div>
    @enderror

    {{-- Upload Form --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <form action="{{ route('leads.import.execute') }}" method="POST" enctype="multipart/form-data" id="importForm">
            @csrf
            <div class="border-2 border-dashed border-slate-300 rounded-2xl p-10 text-center hover:border-accent-400 transition cursor-pointer"
                 onclick="document.getElementById('fileInput').click()"
                 ondragover="event.preventDefault(); this.classList.add('border-accent-400', 'bg-accent-50')"
                 ondragleave="this.classList.remove('border-accent-400', 'bg-accent-50')"
                 ondrop="event.preventDefault(); this.classList.remove('border-accent-400', 'bg-accent-50'); document.getElementById('fileInput').files = event.dataTransfer.files; showFileName();">
                <input type="file" name="file" id="fileInput" class="hidden"
                       accept=".csv,.txt,.xlsx,.xls"
                       onchange="showFileName()">
                <svg class="w-12 h-12 mx-auto text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                </svg>
                <p class="text-slate-600 font-medium" id="uploadText">{{ __('Klik atau seret file ke sini') }}</p>
                <p class="text-sm text-slate-400 mt-1">{{ __('Format: .xlsx, .xls, .csv (maks 10MB)') }}</p>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <a href="{{ route('leads.index') }}"
                   class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 text-center">
                    {{ __('Batal') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </a>
                <button type="submit" id="submitBtn"
                        class="group relative overflow-hidden px-6 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white text-sm font-medium transition-all duration-300 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Import Sekarang') }}
                    <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                </button>
            </div>
        </form>

        {{-- Format Info --}}
        <div class="mt-8 pt-6 border-t border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 mb-3">{{ __('Format Header yang Didukung:') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Data Customer') }}</p>
                    <ul class="text-sm text-slate-700 space-y-1">
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">nama_perusahaan</code> <span class="text-red-500">*</span></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">alamat</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">telp / telepon</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">wa / whatsapp</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">email</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">pic / contact_person</code></li>
                    </ul>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Data Lead') }}</p>
                    <ul class="text-sm text-slate-700 space-y-1">
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">segment</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">source / masuk_by</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">kebutuhan</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">solusi</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">progress / followup</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">catatan / notes</code></li>
                    </ul>
                </div>
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('Data Lainnya') }}</p>
                    <ul class="text-sm text-slate-700 space-y-1">
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">tanggal / date</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">pt / pt_group</code></li>
                        <li><code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs">sales / assigned_to</code></li>
                    </ul>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3">{{ __('* = wajib diisi. Nama header tidak case-sensitive. Jika customer sudah ada (berdasarkan nama), akan digunakan yang sudah ada.') }}</p>
        </div>
    </div>
</x-app-layout>

<script>
    function showFileName() {
        var input = document.getElementById('fileInput');
        var text = document.getElementById('uploadText');
        if (input.files.length > 0) {
            text.textContent = input.files[0].name;
            text.classList.add('text-accent-600');
        }
    }
</script>
