        {{-- Tabel --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-600">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">Partner</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Tipe') }}</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Kontak') }}</th>
                        <th class="px-6 py-3.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Jumlah Lead') }}</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-slate-200 uppercase tracking-wider">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-600/70">
                    @forelse($partners as $partner)
                        @php
                            $dotColor = \App\Models\Partner::TYPE_DOTS[$partner->type] ?? '#94a3b8';
                            $palette = ['#3b82f6', '#8b5cf6', '#14b8a6', '#f43f5e', '#f59e0b', '#10b981'];
                            $avatarBg = $palette[abs(crc32($partner->name)) % count($palette)];
                            $initials = collect(explode(' ', trim($partner->name)))
                                ->filter()
                                ->take(2)
                                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                                ->implode('');
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                          style="background-color: {{ $avatarBg }}">{{ $initials }}</span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-800 dark:text-slate-100 truncate max-w-[220px]">{{ $partner->name }}</div>
                                        @if($partner->address)
                                            <div class="text-xs text-slate-400 truncate max-w-[240px]">{{ $partner->address }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium
                                             bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $dotColor }}"></span>
                                    {{ __(\App\Models\Partner::TYPES[$partner->type] ?? $partner->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($partner->contact_person || $partner->phone || $partner->email)
                                    <div class="space-y-0.5">
                                        @if($partner->contact_person)
                                            <div class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $partner->contact_person }}</div>
                                        @endif
                                        @if($partner->phone)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $partner->phone }}</div>
                                        @endif
                                        @if($partner->email)
                                            <div class="text-xs text-slate-400 truncate max-w-[220px]">{{ $partner->email }}</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($partner->leads_count > 0)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                        {{ $partner->leads_count }} lead
                                    </span>
                                @else
                                    <span class="text-slate-400 text-sm">0</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    @can('manage-marketing')
                                        <x-icon-button as="a" icon="edit" href="{{ route('partners.edit', $partner) }}" title="Edit partner" />
                                        <form action="{{ route('partners.destroy', $partner) }}" method="POST"
                                              onsubmit="return confirm('{{ __('Hapus partner') }} {{ $partner->name }}?')" class="inline">
                                            @csrf @method('DELETE')
                                            <x-icon-button icon="delete" type="submit" title="{{ __('Hapus partner') }}" />
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="mx-auto w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                                    </svg>
                                </div>
                                <p class="text-slate-500 font-medium">
                                    {{ request()->filled('search') || request()->filled('type') ? __('Tidak ada partner yang cocok dengan filter.') : __('Belum ada data partner.') }}
                                </p>
                                @can('manage-marketing')
                                    <div class="mt-4 flex justify-center">
                                        <x-icon-button as="a" icon="add" href="{{ route('partners.create') }}" label="{{ __('Tambah Partner Pertama') }}" title="{{ __('Tambah Partner Pertama') }}" />
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($partners->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-600">{{ $partners->links() }}</div>
        @endif
