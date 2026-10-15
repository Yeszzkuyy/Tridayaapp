<x-app-layout>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Monitoring</h1>
            <p class="text-slate-500 mt-1">{{ __('Rekap lead dan aktivitas terakhir tim Marketing & Sales') }}</p>
        </div>
        <x-icon-button as="a" icon="back" href="{{ route('leads.index') }}" title="{{ __('Kembali') }}" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 overflow-x-auto">
        @if($summary->flatten()->isEmpty())
            <p class="text-center text-sm text-slate-500 py-12">{{ __('Belum ada anggota tim.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200">
                        <th class="py-3 px-3 font-medium">{{ __('Nama') }}</th>
                        @foreach($statuses as $status)
                            <th class="py-3 px-3 font-medium text-center">{{ ucfirst($status) }}</th>
                        @endforeach
                        <th class="py-3 px-3 font-medium text-center">Total</th>
                        <th class="py-3 px-3 font-medium">{{ __('Aktivitas Terakhir') }}</th>
                        <th class="py-3 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary as $division => $rows)
                        <tr>
                            <td colspan="{{ count($statuses) + 4 }}" class="pt-5 pb-1 px-3">
                                <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {{ $division }}
                                </span>
                            </td>
                        </tr>
                        @foreach($rows as $row)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 px-3 font-semibold text-slate-800">{{ $row->user->name }}</td>
                                @foreach($statuses as $status)
                                    <td class="py-3 px-3 text-center
                                        {{ $status === 'won' ? 'text-green-600' : ($status === 'lost' ? 'text-red-500' : 'text-slate-600') }}">
                                        {{ $row->counts[$status] }}
                                    </td>
                                @endforeach
                                <td class="py-3 px-3 text-center font-bold text-accent-600">{{ $row->total }}</td>
                                <td class="py-3 px-3 text-slate-500">
                                    {{ $row->lastActivityAt ? $row->lastActivityAt->diffForHumans() : __('Belum ada aktivitas') }}
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <a href="{{ route('leads.activities', ['user' => $row->user->id]) }}"
                                       class="text-xs font-medium text-accent-600 hover:underline whitespace-nowrap">
                                        {{ __('Lihat Aktivitas') }} &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
