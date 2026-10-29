<x-app-layout>
    @php
        $modeStyles = [
            'online' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
            'offline' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            // Nilai lama.
            'office' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'field' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 dark:text-slate-100">{{ $meeting->customer?->name ?? '-' }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-slate-500 dark:text-slate-400">
                <span>{{ $meeting->follow_up_date?->translatedFormat('l, d M Y') ?? '-' }}</span>
                @if($meeting->activity_time)
                    <span>• {{ substr((string) $meeting->activity_time, 0, 5) }}</span>
                @endif
                @if($meeting->meeting_mode)
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $modeStyles[$meeting->meeting_mode] ?? 'bg-slate-100 text-slate-600' }}">{{ \App\Models\FollowUp::meetingModeLabel($meeting->meeting_mode) }}</span>
                @endif
                @if($meeting->completed_at)
                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ __('Completed') }}</span>
                @else
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Report Pending') }}</span>
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if(! $meeting->completed_at)
                <x-icon-button as="a" icon="add" href="{{ route('sales.meetings.report', $meeting) }}" label="{{ __('Input Report') }}" title="{{ __('Input Report') }}" />
            @endif
            <x-icon-button as="a" icon="back" href="{{ route('sales.meetings.index') }}" title="{{ __('Back') }}" />
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
            <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <p class="text-slate-500">{{ __('Customer') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $meeting->customer?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Created by') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $meeting->creator?->name ?? '-' }}</span></p>
                <p class="text-slate-500">{{ __('Lead') }}:
                    <span class="font-medium text-slate-800 dark:text-slate-100">
                        @if($meeting->lead)
                            <a href="{{ route('leads.show', $meeting->lead) }}" class="text-accent-600 hover:underline dark:text-accent-400">#{{ $meeting->lead->id }} — {{ $meeting->lead->customer?->name }}</a>
                        @else
                            -
                        @endif
                    </span>
                </p>
                <p class="text-slate-500">{{ __('Next Follow-up') }}: <span class="font-medium text-slate-800 dark:text-slate-100">{{ $meeting->meeting_next_action_date?->format('d M Y') ?? '-' }}</span></p>
            </div>

            @if($meeting->location)
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Location') }}</p>
                    <x-location-link :location="$meeting->location" class="mt-1" />
                </div>
            @endif

            <div class="mt-4">
                <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Summary Meeting') }}</p>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $meeting->description ?: '-' }}</p>
            </div>
        </div>

        {{-- Foto bukti tampil sejak meeting dibuat, tidak menunggu laporan selesai. --}}
        @if(!empty($meeting->meeting_photos))
            @php
                $meetingPhotos = is_array($meeting->meeting_photos) ? $meeting->meeting_photos : [];
                $photoCount = count($meetingPhotos);
                $photoLimit = \App\Models\FollowUp::MAX_MEETING_PHOTOS;
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Meeting Proof Photos') }}</h2>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $photoCount }}/{{ $photoLimit }} {{ __('photos') }}</span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($meetingPhotos as $index => $photo)
                        <a href="{{ asset('storage/'.$photo) }}" target="_blank" rel="noopener noreferrer"
                           class="group relative aspect-video overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm dark:border-slate-700">
                            <img src="{{ asset('storage/'.$photo) }}" alt="{{ __('Meeting documentation') }} {{ $index + 1 }}"
                                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            <span class="absolute bottom-1 right-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">{{ $index + 1 }}</span>
                        </a>
                    @endforeach
                </div>

                @if(! $meeting->completed_at)
                    <p class="mt-3 text-xs text-slate-400">{{ __('These photos were uploaded when the meeting was created.') }}</p>
                @endif
            </div>
        @endif

        @if($meeting->hasMeetingReport())
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Meeting Report & Minutes of Meeting (MOM)') }}</h2>
                    @if($meeting->meeting_outcome)
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ \App\Models\FollowUp::outcomeBadgeClass($meeting->meeting_outcome) }}">
                            {{ \App\Models\FollowUp::outcomeLabel($meeting->meeting_outcome) }}
                        </span>
                    @endif
                </div>

                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $meeting->meeting_notes }}</p>

                @if($meeting->completed_at)
                    <p class="mt-4 text-xs text-slate-400">{{ __('Reported at :time', ['time' => $meeting->completed_at->setTimezone('Asia/Jakarta')->format('d M Y H:i')]) }}</p>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
