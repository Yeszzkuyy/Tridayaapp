<x-app-layout>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div><h1 class="text-3xl font-bold text-content-primary dark:text-white">{{ $purchaseOrder->po_number }}</h1><p class="text-content-secondary mt-1">{{ $purchaseOrder->customer->name }}</p></div>
            <div class="flex items-center gap-2">
                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">{{ __('Legacy — read only') }}</span>
                <x-back-button href="{{ route('admin.pos.index') }}" />
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-xs text-content-tertiary">{{ __('PO No.') }}</dt><dd class="font-mono font-semibold text-content-primary">{{ $purchaseOrder->po_number }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Customer') }}</dt><dd class="font-medium text-content-primary">{{ $purchaseOrder->customer->name }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Project') }}</dt><dd class="font-medium text-content-primary">{{ $purchaseOrder->project?->project_name ?? '-' }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Issue Date') }}</dt><dd class="font-medium text-content-primary">{{ $purchaseOrder->issue_date->format('d M Y') }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Amount') }}</dt><dd class="font-semibold text-lg text-content-primary">Rp {{ number_format($purchaseOrder->amount, 0, ',', '.') }}</dd></div>
                <div><dt class="text-xs text-content-tertiary">{{ __('Status') }}</dt>
                    <dd><span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                        @if($purchaseOrder->status === 'selesai') bg-green-100 text-green-700
                        @elseif($purchaseOrder->status === 'dibatalkan') bg-red-100 text-red-700
                        @elseif($purchaseOrder->status === 'diproses') bg-yellow-100 text-yellow-700
                        @else bg-slate-100 text-content-secondary @endif">{{ $purchaseOrder->statusLabel() }}</span></dd>
                </div>
            </dl>
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-content-secondary uppercase tracking-wider mb-3">{{ __('Item') }}</h3>
                <p class="text-content-secondary whitespace-pre-wrap">{{ $purchaseOrder->items }}</p>
            </div>
            @if($purchaseOrder->notes)
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-content-secondary uppercase tracking-wider mb-3">{{ __('Notes') }}</h3>
                <p class="text-content-secondary whitespace-pre-wrap">{{ $purchaseOrder->notes }}</p>
            </div>
            @endif
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-content-secondary uppercase tracking-wider mb-3">{{ __('PO File') }}</h3>
                @forelse($purchaseOrder->documents as $document)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-content-primary">{{ $document->file_name }}</p>
                            <p class="text-xs text-content-tertiary">{{ $document->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-file-preview :url="route('project-documents.preview', $document)"
                                            :download="route('project-documents.download', $document)"
                                            :name="$document->file_name" />
                            <x-icon-button as="a" icon="download" href="{{ route('project-documents.download', $document) }}" title="{{ __('Download') }}" />
                        </div>
                    </div>
                @empty
                    <p class="text-content-tertiary text-sm text-center py-4">{{ __('No file uploaded yet.') }}</p>
                @endforelse
            </div>
            <div class="mt-4 text-xs text-content-tertiary">{{ __('Created by:') }} {{ $purchaseOrder->creator?->name ?? '-' }}</div>
        </div>
    </div>
</x-app-layout>
