<x-app-layout>
    <div class="w-full space-y-6" x-data="workLogRecap(@js($summaryText), @js($days))">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Rekap Progres Kerja') }}
                    </h1>
                    <span class="inline-flex items-center rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                        WhatsApp 2-Harian
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Draf otomatis ditarik dari update sistem & commit terbaru (Yeski & Raafi). Tinggal klik salin dan kirim ke WhatsApp Senior.') }}
                </p>
            </div>

            {{-- Period Selector Tabs --}}
            <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <template x-for="p in [1, 2, 3, 7]" :key="p">
                    <button type="button"
                            @click="changeDays(p)"
                            :class="days === p ? 'bg-accent-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all duration-200">
                        <span x-text="p === 1 ? 'Hari Ini' : p + ' Hari Terakhir'"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Petunjuk Cepat --}}
        <div class="rounded-2xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/70 to-teal-50/50 p-4 dark:border-emerald-900/50 dark:from-emerald-950/20 dark:to-teal-950/10">
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </span>
                <div class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                    <strong class="font-semibold text-slate-900 dark:text-white">Cara Pakai Cepat:</strong>
                    Teks di bawah ini sudah otomatis dibuatkan oleh sistem berdasarkan update commit terbaru. Cukup klik tombol <span class="font-semibold text-emerald-700 dark:text-emerald-400">"Salin Pesan WhatsApp"</span>, lalu paste ke chat WhatsApp Pak Senior. Untuk screenshot hasil pengerjaan, Anda tinggal melampirkannya langsung di chat WhatsApp tersebut.
                </div>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            {{-- Kolom Kiri: Pesan WhatsApp Siap Salin (7 Cols) --}}
            <div class="space-y-6 lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Format Pesan WhatsApp (Siap Kirim)</h2>
                                <p class="text-xs text-slate-400 dark:text-slate-500">Otomatis terisi, dapat diedit jika ingin menambahkan catatan</p>
                            </div>
                        </div>

                        {{-- Tombol Salin Cepat Utama --}}
                        <button type="button"
                                @click="copyToClipboard()"
                                class="inline-flex items-center gap-2 rounded-xl bg-accent-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md transition-all duration-200 hover:bg-accent-700 hover:shadow-accent-500/30 active:scale-95">
                            <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <svg x-show="copied" x-cloak class="h-4 w-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span x-text="copied ? '✅ Berhasil Disalin!' : '📋 Salin Pesan WhatsApp'"></span>
                        </button>
                    </div>

                    {{-- Textarea Preview --}}
                    <div class="mt-4 relative">
                        <textarea x-model="summaryText"
                                  rows="15"
                                  class="w-full rounded-xl border-slate-200 bg-slate-50/70 p-4 font-mono text-xs leading-relaxed text-slate-800 shadow-inner focus:border-accent-500 focus:bg-white focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:focus:bg-slate-900"></textarea>
                    </div>

                    {{-- Tombol Aksi Bawah --}}
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <button type="button"
                                @click="regenerate()"
                                :disabled="loading"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/60 transition">
                            <svg :class="{'animate-spin': loading}" class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span x-text="loading ? 'Memuat Ulang...' : 'Muat Ulang dari Git'"></span>
                        </button>

                        <button type="button"
                                @click="openWhatsAppDirect()"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                            </svg>
                            <span>Buka WhatsApp Langsung</span>
                        </button>
                    </div>
                </div>

                {{-- Penyesuaian Opsional: Kendala & Next Steps --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">
                        Kustomisasi Catatan Tambahan (Opsional)
                    </h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">
                        Isi di bawah ini jika ingin mengubah keterangan kendala atau target berikutnya, draf teks di atas akan otomatis terupdate.
                    </p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                                Kendala & Catatan:
                            </label>
                            <input type="text"
                                   x-model="kendalaInput"
                                   @input.debounce.400ms="regenerate()"
                                   placeholder="Default: Tidak ada kendala teknis yang menghambat"
                                   class="w-full rounded-xl border-slate-200 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                                Next Steps (Target 2 Hari Ke Depan):
                            </label>
                            <textarea rows="2"
                                      x-model="nextStepsInput"
                                      @input.debounce.400ms="regenerate()"
                                      placeholder="Default: Lanjut pengujian alur modul dan pemeliharaan fitur"
                                      class="w-full rounded-xl border-slate-200 text-xs focus:border-accent-500 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Daftar Git Commits yang Terdeteksi (5 Cols) --}}
            <div class="space-y-6 lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-accent-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                Commit Git Terdeteksi (<span x-text="commits.length"></span>)
                            </h3>
                        </div>
                        <span class="text-xs text-slate-400" x-text="days + ' hari terakhir'"></span>
                    </div>

                    <div class="mt-3 space-y-2.5 max-h-[640px] overflow-y-auto pr-1">
                        <template x-for="(c, idx) in commits" :key="idx">
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700/60 dark:bg-slate-900/40 dark:hover:border-slate-600">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center rounded-md bg-accent-50 px-2 py-0.5 text-[10px] font-semibold text-accent-600 dark:bg-accent-950/40 dark:text-accent-400"
                                          x-text="c.category"></span>
                                    <span class="font-mono text-[10px] text-slate-400" x-text="c.hash + ' • ' + c.date"></span>
                                </div>
                                <p class="mt-1.5 text-xs font-medium text-slate-800 dark:text-slate-200 leading-snug"
                                   x-text="c.summary"></p>
                                <div class="mt-1 flex items-center justify-between text-[10px] text-slate-400">
                                    <span x-text="'Oleh: ' + c.author"></span>
                                </div>
                            </div>
                        </template>

                        <div x-show="commits.length === 0" class="py-8 text-center text-xs text-slate-400">
                            Tidak ada commit git dalam periode ini.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Script Alpine untuk interaktivitas instan --}}
    <script>
        function workLogRecap(initialSummary, initialDays) {
            return {
                summaryText: initialSummary,
                days: initialDays,
                commits: @json($commits),
                loading: false,
                copied: false,
                buktiInput: '',
                kendalaInput: '',
                nextStepsInput: '',

                changeDays(p) {
                    this.days = p;
                    this.regenerate();
                },

                copyToClipboard() {
                    if (!navigator.clipboard) {
                        const el = document.createElement('textarea');
                        el.value = this.summaryText;
                        document.body.appendChild(el);
                        el.select();
                        document.execCommand('copy');
                        document.body.removeChild(el);
                    } else {
                        navigator.clipboard.writeText(this.summaryText);
                    }
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2500);
                },

                openWhatsAppDirect() {
                    const encoded = encodeURIComponent(this.summaryText);
                    window.open('https://api.whatsapp.com/send?text=' + encoded, '_blank');
                },

                regenerate() {
                    this.loading = true;
                    fetch('{{ route('dev.worklog.generate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            days: this.days,
                            bukti: this.buktiInput ? ('• ' + this.buktiInput) : null,
                            kendala: this.kendalaInput ? ('• ' + this.kendalaInput) : null,
                            next_steps: this.nextStepsInput ? this.formatNextSteps(this.nextStepsInput) : null,
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.loading = false;
                        if (data.status === 'success') {
                            this.summaryText = data.summary;
                            this.commits = data.commits || [];
                        }
                    })
                    .catch(() => {
                        this.loading = false;
                    });
                },

                formatNextSteps(text) {
                    return text.split('\n')
                        .map(line => line.trim())
                        .filter(line => line.length > 0)
                        .map(line => line.startsWith('•') ? line : ('• ' + line))
                        .join('\n');
                }
            }
        }
    </script>
</x-app-layout>
