<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $proposal->proposal_number }}</h1>
                <p class="text-slate-500 mt-1">
                    {{ \App\Models\Proposal::statusLabel($proposal->status) }} •
                    Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-icon-button as="a" icon="back" href="{{ route('leads.show', $proposal->lead) }}" title="{{ __('Back') }}" />
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-600 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <p class="text-slate-500">{{ __('Customer') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->customer }}</span></p>
                <p class="text-slate-500">{{ __('Tanggal') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->proposal_date?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Berlaku Sampai') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->valid_until?->format('d M Y') ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Dibuat oleh') }}: <span class="text-slate-800 dark:text-slate-100 font-medium">{{ $proposal->creator?->name ?? '-' }}</span></p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-200 dark:border-slate-700">
                            <th class="py-2 pr-2">{{ __('Deskripsi') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Qty') }}</th>
                            <th class="py-2 pr-2 text-right">{{ __('Harga') }}</th>
                            <th class="py-2 text-right">{{ __('Subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proposal->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-700">
                                <td class="py-2 pr-2">{{ $item->description }} <span class="text-xs text-slate-400">({{ $item->unit }})</span></td>
                                <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 pr-2 text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-2 text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="w-full md:w-64 text-sm space-y-1">
                    <p class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>{{ number_format($proposal->subtotal, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Diskon') }}</span><span>{{ number_format($proposal->discount, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between text-slate-500"><span>{{ __('Pajak') }}</span><span>{{ number_format($proposal->tax, 0, ',', '.') }}</span></p>
                    <p class="flex justify-between font-bold text-slate-800 dark:text-slate-100"><span>{{ __('Grand Total') }}</span><span>Rp {{ number_format($proposal->grand_total, 0, ',', '.') }}</span></p>
                </div>
            </div>

            @if($proposal->notes)
                <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $proposal->notes }}</p>
            @endif

            <div class="flex flex-wrap gap-2 pt-2">
                <a href="{{ route('sales.proposals.preview', $proposal) }}" target="_blank"
                   class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-medium transition">{{ __('Preview') }}</a>
                @if(in_array($proposal->status, ['draft', 'revision']))
                    <a href="{{ route('sales.proposals.edit', $proposal) }}"
                       class="px-4 py-2 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm font-medium transition">{{ __('Edit') }}</a>
                @endif
                @if($proposal->status === 'draft')
                    <form action="{{ route('sales.proposals.ready', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-accent-600 hover:bg-accent-500 text-white text-sm font-medium transition">{{ __('Ready') }}</button>
                    </form>
                    <form action="{{ route('sales.proposals.submit-technical', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Serahkan draft ini ke tim teknis?') }}')">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-purple-100 hover:bg-purple-200 text-purple-700 text-sm font-medium transition">{{ __('Ke Teknis') }}</button>
                    </form>
                    <form action="{{ route('sales.proposals.cancel', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Batalkan proposal ini?') }}')">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium transition">{{ __('Cancel') }}</button>
                    </form>
                @endif
                @if($proposal->status === 'pending_technical')
                    <p class="text-sm text-purple-700 dark:text-purple-300">{{ __('Menunggu penyesuaian tim teknis.') }}</p>
                    @canany(['manage-technician', 'manage-admin'])
                        <form action="{{ route('sales.proposals.complete-technical', $proposal) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium transition">{{ __('Selesai & Siapkan') }}</button>
                        </form>
                        <form action="{{ route('sales.proposals.return-technical', $proposal) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl bg-yellow-100 hover:bg-yellow-200 text-yellow-800 text-sm font-medium transition">{{ __('Kembalikan') }}</button>
                        </form>
                    @endcanany
                @endif
                @if($proposal->status === 'ready')
                    <form action="{{ route('sales.proposals.send', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Tandai proposal ini terkirim?') }}')">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium transition">{{ __('Send') }}</button>
                    </form>
                @endif
                @if(in_array($proposal->status, ['ready', 'sent', 'viewed']) && ($wa = $proposal->lead->customer?->whatsapp))
                    @php
                        $waNumber = preg_replace('/\D/', '', $wa);
                        if (str_starts_with($waNumber, '0')) $waNumber = '62' . substr($waNumber, 1);
                        $waText = __('Halo, berikut penawaran *{number}* senilai Rp {total} (berlaku sampai {until}). Mohon konfirmasinya, terima kasih.', [
                            'number' => $proposal->proposal_number,
                            'total' => number_format($proposal->grand_total, 0, ',', '.'),
                            'until' => $proposal->valid_until?->format('d M Y') ?? '-',
                        ]);
                    @endphp
                    <a href="https://wa.me/{{ $waNumber }}?text={{ urlencode($waText) }}" target="_blank" rel="noopener"
                       class="px-4 py-2 rounded-xl bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium transition">{{ __('Kirim via WhatsApp') }}</a>
                @endif
                @if($proposal->status === 'sent')
                    <form action="{{ route('sales.proposals.viewed', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-medium transition">{{ __('Viewed') }}</button>
                    </form>
                @endif
                @if(in_array($proposal->status, ['sent', 'viewed']))
                    <form action="{{ route('sales.proposals.revise', $proposal) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm font-medium transition">{{ __('Revision') }}</button>
                    </form>
                    <form action="{{ route('sales.proposals.respond', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Tandai proposal ini diterima customer?') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="accepted">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium transition">{{ __('Accept') }}</button>
                    </form>
                    <form action="{{ route('sales.proposals.respond', $proposal) }}" method="POST" class="inline"
                          onsubmit="return confirm('{{ __('Tandai proposal ini ditolak customer?') }}')">
                        @csrf
                        <input type="hidden" name="decision" value="rejected">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium transition">{{ __('Reject') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
