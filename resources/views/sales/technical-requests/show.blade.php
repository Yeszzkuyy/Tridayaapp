<x-app-layout>
    @if(session('success') && session('success_card'))
        <x-status-card :message="session('success')" />
    @endif

    @php
        $user = auth()->user();
        $fromTasks = $fromTasks ?? false;
        $readOnly = $readOnly ?? false;
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;
        $isTechnicianArea = $user?->can('view-technician') ?? false;
        $isCoordinator = $user?->hasAnyRole(['lead-technician', 'super-admin']) ?? false;
        $backUrl = $fromTasks
            ? route('teknisi.tasks.index')
            : ($isTechnicianArea
                ? route('teknisi.requests.index', ['tab' => 'engineer'])
                : route('sales.requests.index', ['tab' => 'engineer']));
        $leadLabel = $technicalRequest->lead?->customer?->name ?? __('Lead').' #'.$technicalRequest->lead_id;
        $customer = $technicalRequest->lead?->customer;
        $displayLocation = $technicalRequest->location ?: $customer?->address;
        $displayPicName = $technicalRequest->pic_contact_name ?: $customer?->picName();
        $displayPicPhone = $technicalRequest->pic_contact_phone ?: ($customer?->whatsapp ?: $customer?->phone);
        $attachmentName = $technicalRequest->attachment_path
            ? preg_replace('/^\d+_/', '', basename($technicalRequest->attachment_path))
            : null;
        $statusColors = [
            'waiting_lead' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'accepted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'assigned' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
        ];
        $canReview = $isCoordinator && $technicalRequest->status === 'waiting_lead';
        $canAssign = $isCoordinator && in_array($technicalRequest->status, ['accepted', 'assigned'], true);
        $canWork = ($isSuperAdmin || $technicalRequest->isAssignedTo($user))
            && in_array($technicalRequest->status, ['assigned', 'in_progress'], true);
        $canCancel = !in_array($technicalRequest->status, ['completed', 'cancelled'], true)
            && ((int) $technicalRequest->requested_by === (int) $user?->id || $user?->can('manage-sales-leads'));
        $canCreateQuotation = $technicalRequest->status === 'completed' && ($user?->can('manage-sales') ?? false);
        $hasActions = ! $readOnly && ($canReview || $canAssign || $canWork || $canCancel || $canCreateQuotation);
        $hasWorkDetails = collect([
            $technicalRequest->purpose,
            $displayLocation,
            $displayPicName,
            $displayPicPhone,
            $technicalRequest->requirement,
            $technicalRequest->problem_description,
            $technicalRequest->scope,
            $technicalRequest->notes,
        ])->contains(fn ($value) => filled($value));
        $survey = $technicalRequest->survey;
        $surveyHasReport = $survey?->hasReport() ?? false;
        $installation = $technicalRequest->installation;
        $installationHasReport = $installation?->hasReport() ?? false;
    @endphp

    <div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg bg-accent-50 px-2.5 py-1 text-xs font-semibold text-accent-700 dark:bg-accent-500/15 dark:text-accent-300">
                        {{ \App\Models\TechnicalRequest::typeLabel($technicalRequest->request_type) }}
                    </span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColors[$technicalRequest->status] ?? 'bg-slate-200 text-slate-700' }}">
                        {{ \App\Models\TechnicalRequest::statusLabel($technicalRequest->status) }}
                    </span>
                    @if($technicalRequest->priority === 'urgent')
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ __('Urgent') }}</span>
                    @endif
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">#{{ $technicalRequest->id }}</span>
                </div>
                <h1 class="mt-3 break-words text-2xl font-bold text-slate-800 dark:text-slate-100 sm:text-3xl">{{ $technicalRequest->title }}</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Lead') }}:
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $leadLabel }}</span>
                </p>
            </div>
            <x-icon-button as="a" icon="back" href="{{ $backUrl }}" title="{{ __('Back') }}" />
        </div>

        {{-- Workflow pekerjaan lapangan --}}
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="field-job-workflow-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="field-job-workflow-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Workflow Progress') }}</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                    {{ $workflow['done'] }}/{{ $workflow['total'] }} {{ __('steps completed') }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($workflow['steps'] as $index => $step)
                    <div class="flex items-start gap-2.5 rounded-xl border p-3 {{ $step['done'] ? 'border-green-200 bg-green-50 dark:border-green-800/60 dark:bg-green-900/20' : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/30' }}">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step['done'] ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300' }}">
                            @if($step['done'])
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $step['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $step['detail'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($fromTasks && $readOnly)
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-accent-200 bg-accent-50 px-4 py-3 dark:border-accent-500/30 dark:bg-accent-500/10">
                <x-icon name="eye" class="mt-0.5 h-4 w-4 shrink-0 text-accent-600 dark:text-accent-300" />
                <div>
                    <p class="text-sm font-semibold text-accent-800 dark:text-accent-200">{{ __('Read-only task view') }}</p>
                    <p class="mt-0.5 text-sm leading-5 text-accent-700 dark:text-accent-300">
                        {{ __('This task is read-only. Contact your Lead Technician to change the schedule or assignment.') }}
                    </p>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-details-heading">
                <h2 id="technical-request-details-heading" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request details') }}</h2>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                        <x-user-avatar :user="$technicalRequest->requester" size="w-11 h-11" text="text-sm" />
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Requested by') }}</p>
                            <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $technicalRequest->requester?->name ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                            @if($technicalRequest->technician)
                                <x-user-avatar :user="$technicalRequest->technician" size="w-11 h-11" text="text-sm" color="green" />
                            @else
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">
                                    <x-icon name="tools" class="h-5 w-5" />
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Technician') }}</p>
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $technicalRequest->technician?->name ?? __('Not assigned yet') }}</p>
                            </div>
                        </div>

                        <div class="flex min-w-0 items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300" aria-hidden="true">
                                <x-icon name="tools" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-300">{{ __('Support Technicians') }}</p>
                                @if($technicalRequest->supportUsers->isNotEmpty())
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach($technicalRequest->supportUsers as $support)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                                <x-user-avatar :user="$support" size="h-5 w-5" text="text-[9px]" :clickable="false" />
                                                {{ $support->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('No support engineers yet') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 text-sm dark:border-slate-600 {{ $technicalRequest->completed_at ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-2' }}">
                    <div>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Created') }}</dt>
                        <dd class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $technicalRequest->created_at?->format('d M Y, H:i') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Target') }}</dt>
                        <dd class="mt-1 font-medium text-slate-800 dark:text-slate-100">
                            {{ $technicalRequest->target_date?->format('d M Y') ?? '-' }}
                            @if($technicalRequest->target_time)
                                <span class="text-slate-500 dark:text-slate-400">· {{ substr((string) $technicalRequest->target_time, 0, 5) }}</span>
                            @endif
                        </dd>
                    </div>
                    @if($technicalRequest->completed_at)
                        <div>
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Completed') }}</dt>
                            <dd class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $technicalRequest->completed_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if($customer)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-customer-heading">
                    <h2 id="technical-request-customer-heading" class="mb-4 text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Customer details') }}</h2>
                    <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Customer') }}</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $customer->name }}</p>
                        </div>
                        @if($customer->company)
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Company') }}</p>
                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $customer->company }}</p>
                            </div>
                        @endif
                        @if($customer->phone || $customer->whatsapp)
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Phone') }}</p>
                                <p class="mt-1 text-slate-700 dark:text-slate-200">
                                    <a href="tel:{{ $customer->whatsapp ?: $customer->phone }}" class="font-medium text-accent-600 hover:underline dark:text-accent-400">
                                        {{ $customer->whatsapp ?: $customer->phone }}
                                    </a>
                                </p>
                            </div>
                        @endif
                        @if($customer->email)
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Email') }}</p>
                                <p class="mt-1 break-all text-slate-700 dark:text-slate-200">
                                    <a href="mailto:{{ $customer->email }}" class="font-medium text-accent-600 hover:underline dark:text-accent-400">{{ $customer->email }}</a>
                                </p>
                            </div>
                        @endif
                        @if($customer->address)
                            <div class="md:col-span-2">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Address') }}</p>
                                <x-location-link :location="$customer->address" class="mt-1" />
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if($hasWorkDetails)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-work-heading">
                    <h2 id="technical-request-work-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Work details') }}</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        @if($technicalRequest->purpose)
                            <div class="md:col-span-2">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Purpose') }}</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->purpose }}</p>
                            </div>
                        @endif
                        @if($displayLocation)
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Location') }}</p>
                                <x-location-link :location="$displayLocation" class="mt-1" />
                            </div>
                        @endif
                        @if($displayPicName || $displayPicPhone)
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('PIC Contact') }}</p>
                                <p class="mt-1 break-words text-sm text-slate-700 dark:text-slate-200">
                                    {{ $displayPicName ?: '-' }}
                                    @if($displayPicPhone)
                                        <span aria-hidden="true">·</span>
                                        <a href="tel:{{ $displayPicPhone }}" class="font-medium text-accent-600 hover:underline dark:text-accent-400">{{ $displayPicPhone }}</a>
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>

                    @if($technicalRequest->requirement || $technicalRequest->problem_description || $technicalRequest->scope)
                        <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-600">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('Requirements & Notes') }}</h3>
                            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @if($technicalRequest->requirement)
                                    <div>
                                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Requirement') }}</p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->requirement }}</p>
                                    </div>
                                @endif
                                @if($technicalRequest->problem_description)
                                    <div>
                                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Problem Description') }}</p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->problem_description }}</p>
                                    </div>
                                @endif
                                @if($technicalRequest->scope)
                                    <div>
                                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Scope') }}</p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->scope }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($technicalRequest->notes)
                        <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-600">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->notes }}</p>
                        </div>
                    @endif
                </section>
            @endif

            @if($technicalRequest->attachment_path)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-attachments-heading">
                    <h2 id="technical-request-attachments-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Attachments') }}</h2>
                    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-600 sm:flex-row sm:items-center sm:justify-between">
                        <p class="min-w-0 break-all text-sm font-medium text-slate-800 dark:text-slate-100">{{ $attachmentName }}</p>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-file-preview
                                :url="$fromTasks ? route('teknisi.technical-requests.attachment.preview', $technicalRequest) : route('sales.technical-requests.attachment.preview', $technicalRequest)"
                                :download="$fromTasks ? route('teknisi.technical-requests.attachment', $technicalRequest) : route('sales.technical-requests.attachment', $technicalRequest)"
                                :name="$attachmentName" />
                            <x-icon-button as="a" icon="download" href="{{ $fromTasks ? route('teknisi.technical-requests.attachment', $technicalRequest) : route('sales.technical-requests.attachment', $technicalRequest) }}" label="{{ __('Download Attachment') }}" title="{{ __('Download Attachment') }}" />
                        </div>
                    </div>
                </section>
            @endif

            @if($survey)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-survey-heading">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 id="technical-request-survey-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Related Survey') }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $surveyHasReport ? __('Report has been uploaded') : __('Report not uploaded yet') }}
                            </p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('teknisi.surveys.show', $survey) }}" label="{{ $survey->title }}" title="{{ $survey->title }}" />
                    </div>
                </section>
            @endif

            @if($installation)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-installation-heading">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 id="technical-request-installation-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Related Installation') }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $installationHasReport ? __('Report has been uploaded') : __('Report not uploaded yet') }}
                                · {{ $installation->isBastReady() ? __('BAST signed') : __('BAST not signed yet') }}
                            </p>
                        </div>
                        <x-icon-button as="a" icon="view" href="{{ route('teknisi.instalasis.show', $installation) }}" label="{{ __('Installation Detail') }}" title="{{ __('Installation Detail') }}" />
                    </div>
                </section>
            @endif

            @if($technicalRequest->technical_result)
                <section class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm dark:border-green-800 dark:bg-green-900/20 sm:p-6" aria-labelledby="technical-request-result-heading">
                    <h2 id="technical-request-result-heading" class="text-base font-semibold text-green-800 dark:text-green-300">{{ __('Technical Result') }}</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $technicalRequest->technical_result }}</p>
                </section>
            @endif

            @if($hasActions)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:p-6" aria-labelledby="technical-request-actions-heading">
                    <h2 id="technical-request-actions-heading" class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ __('Request actions') }}</h2>
                    <div class="mt-4 space-y-5">
                        @if($canReview)
                            <div class="flex flex-wrap gap-2">
                                <form action="{{ route('sales.technical-requests.review', $technicalRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="accepted">
                                    <x-icon-button type="submit" icon="sparkles" label="{{ __('Accept') }}" title="{{ __('Accept') }}" />
                                </form>
                                <form action="{{ route('sales.technical-requests.review', $technicalRequest) }}" method="POST" class="inline" data-loading-text="{{ __('Processing…') }}"
                                      onsubmit="return confirm('{{ __('Reject this request?') }}')">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <x-icon-button type="submit" icon="delete" label="{{ __('Reject') }}" title="{{ __('Reject') }}" />
                                </form>
                            </div>
                        @endif

                        @if($canAssign)
                            @php
                                $supportOptions = $technicians
                                    ->map(fn ($tech) => ['id' => (int) $tech->id, 'name' => $tech->name])
                                    ->values();
                                $selectedSupportIds = $technicalRequest->supportUsers
                                    ->pluck('id')
                                    ->map(fn ($id) => (int) $id)
                                    ->values();
                            @endphp
                            <form action="{{ route('sales.technical-requests.assign', $technicalRequest) }}" method="POST"
                                  class="border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Assigning…') }}"
                                  x-data="{
                                      mainId: @js((int) old('technician_id', $technicalRequest->assigned_technician_id) ?: null),
                                      supportOpen: false,
                                      supportQuery: '',
                                      supportIds: @js($selectedSupportIds),
                                      supportOptions: @js($supportOptions),
                                      get filteredSupports() {
                                          const q = this.supportQuery.toLowerCase().trim();
                                          return this.supportOptions.filter(t => Number(t.id) !== Number(this.mainId) && (!q || t.name.toLowerCase().includes(q)));
                                      },
                                      get supportSummary() {
                                          const names = this.supportIds.map(id => this.supportOptions.find(t => Number(t.id) === Number(id))?.name).filter(Boolean);
                                          return names.length ? names.join(', ') : '{{ __('Choose support engineers (optional)') }}';
                                      },
                                      syncMain() {
                                          this.supportIds = this.supportIds.filter(id => Number(id) !== Number(this.mainId));
                                      },
                                      toggleSupport(id) {
                                          const i = this.supportIds.indexOf(id);
                                          if (i === -1) this.supportIds.push(id); else this.supportIds.splice(i, 1);
                                      }
                                  }">
                                @csrf
                                <div class="grid grid-cols-1 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Lead Technician') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                                        <select name="technician_id" x-model.number="mainId" @change="syncMain()" required
                                                class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                            <option value="">{{ __('-- Select Technician --') }}</option>
                                            @foreach($technicians as $tech)
                                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('technician_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Support Technicians') }}</label>
                                        <div class="relative" @click.outside="supportOpen = false">
                                            <button type="button" @click="supportOpen = !supportOpen"
                                                    class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-left text-sm dark:border-slate-600 dark:bg-slate-800 focus:border-accent-500 focus:ring-accent-500">
                                                <span class="truncate" :class="supportIds.length ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400'" x-text="supportSummary"></span>
                                                <svg class="h-4 w-4 shrink-0 text-slate-400" :class="supportOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                            </button>

                                            <div x-show="supportOpen" x-cloak x-transition
                                                 class="absolute bottom-full z-30 mb-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-800">
                                                <div class="p-2">
                                                    <input type="text" x-model="supportQuery" placeholder="{{ __('Search support engineer...') }}"
                                                           class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                                </div>
                                                <div class="max-h-52 overflow-y-auto pb-1">
                                                    <template x-for="tech in filteredSupports" :key="tech.id">
                                                        <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm text-slate-700 transition hover:bg-accent-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                                            <input type="checkbox" :value="tech.id" :checked="supportIds.includes(tech.id)"
                                                                   @change="toggleSupport(tech.id)"
                                                                   class="rounded border-slate-300 text-accent-600 focus:ring-accent-500">
                                                            <span class="truncate" x-text="tech.name"></span>
                                                        </label>
                                                    </template>
                                                    <p x-show="filteredSupports.length === 0" class="px-3 py-2 text-sm text-slate-400">{{ __('No results.') }}</p>
                                                </div>
                                            </div>

                                            {{-- Penanda: form selalu mengirim daftar support (boleh kosong). --}}
                                            <input type="hidden" name="support_present" value="1">
                                            <template x-for="id in supportIds" :key="id">
                                                <input type="hidden" name="support_ids[]" :value="id">
                                            </template>
                                        </div>
                                        @error('support_ids') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                        @error('support_ids.*') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit"
                                            class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                        {{ __('Save') }}
                                        <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        @endif

                        @if($canWork)
                            <div class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-600">
                                <form action="{{ route('sales.technical-requests.progress', $technicalRequest) }}" method="POST" class="flex flex-wrap items-end gap-2" data-loading-text="{{ __('Updating…') }}">
                                    @csrf
                                    <div class="min-w-52 flex-1">
                                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Progress Notes') }}</label>
                                        <input type="text" name="notes" value="{{ old('notes', $technicalRequest->notes) }}"
                                               class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">
                                    </div>
                                    <x-icon-button type="submit" icon="edit" label="{{ __('Update Progress') }}" title="{{ __('Update Progress') }}" />
                                </form>

                                <form action="{{ route('sales.technical-requests.result', $technicalRequest) }}" method="POST" class="border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Saving…') }}">
                                    @csrf
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Technical Result') }}</label>
                                    <textarea name="technical_result" rows="4" required
                                              class="w-full rounded-xl border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800">{{ old('technical_result', $technicalRequest->technical_result) }}</textarea>
                                    @error('technical_result') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                    <div class="mt-2">
                                        <button type="submit"
                                                class="group relative overflow-hidden rounded-xl bg-accent-600 px-6 py-2.5 text-sm font-medium text-white transition-all duration-300 hover:bg-accent-700 hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95">
                                            {{ __('Save') }}
                                            <span class="pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </form>

                                <form action="{{ route('sales.technical-requests.complete', $technicalRequest) }}" method="POST" class="border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Completing…') }}"
                                      onsubmit="return confirm('{{ __('Complete this job?') }}')">
                                    @csrf
                                    <x-icon-button type="submit" icon="sparkles" label="{{ __('Complete') }}" title="{{ __('Complete') }}" />
                                </form>
                            </div>
                        @endif

                        @if($canCancel)
                            <form action="{{ route('sales.technical-requests.cancel', $technicalRequest) }}" method="POST" class="border-t border-slate-200 pt-4 dark:border-slate-600" data-loading-text="{{ __('Cancelling…') }}"
                                  onsubmit="return confirm('{{ __('Cancel this request?') }}')">
                                @csrf
                                <x-icon-button type="submit" icon="delete" label="{{ __('Cancel') }}" title="{{ __('Cancel') }}" />
                            </form>
                        @endif

                        @if($canCreateQuotation)
                            <div class="border-t border-slate-200 pt-4 dark:border-slate-600">
                                <x-icon-button as="a" icon="add" href="{{ route('sales.proposals.create', ['lead_id' => $technicalRequest->lead_id]) }}" label="{{ __('Create Quotation') }}" title="{{ __('Create Quotation') }}" />
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
