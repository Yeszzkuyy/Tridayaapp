<?php

use App\Http\Controllers\AccountManagerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadTaskController;
use App\Http\Controllers\ManageSalesController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeAssistantController;
use App\Http\Controllers\ProductKnowledgeController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PocController;
use App\Http\Controllers\PriceRequestController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\ProjectSupportController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SalesRequestHubController;
use App\Http\Controllers\SalesScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TechnicalRequestController;
use App\Http\Controllers\TeknisiRequestController;
use App\Http\Controllers\TechnicianDashboardController;
use App\Http\Controllers\TechnicianScheduleController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\WorkTypeController;
use App\Http\Controllers\WhatsAppCenterController;
use App\Livewire\ProjectStatusList;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard',
    [DashboardController::class, 'index']
)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/cover', [ProfileController::class, 'updateCover'])->name('profile.cover.update');
    Route::delete('/profile/cover', [ProfileController::class, 'removeCover'])->name('profile.cover.remove');

    // Settings (preferensi aplikasi)
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::get('/settings/advanced', [SettingsController::class, 'advanced'])
        ->name('settings.advanced');
    Route::post('/settings/advanced/confirm', [SettingsController::class, 'confirmAdvanced'])
        ->middleware('throttle:10,1')
        ->name('settings.advanced.confirm');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/status', [NotificationController::class, 'status'])->name('notifications.status');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Web Push (VAPID) — simpan/hapus subscription browser per user.
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // ============================================
    // AI ASSISTANT (OfficeAssistant)
    // ============================================
    Route::get('/ai/assistant', [OfficeAssistantController::class, 'index'])->name('ai.assistant.index');
    // LLM berbayar + upload 30MB: throttle agar tidak di-spam.
    Route::post('/ai/assistant', [OfficeAssistantController::class, 'send'])
        ->middleware('throttle:30,1')
        ->name('ai.assistant.send');
    Route::get('/ai/assistant/conversations/{conversation}', [OfficeAssistantController::class, 'showConversation'])->name('ai.assistant.conversations.show');
    Route::patch('/ai/assistant/conversations/{conversation}', [OfficeAssistantController::class, 'renameConversation'])->name('ai.assistant.conversations.rename');
    Route::delete('/ai/assistant/conversations/{conversation}', [OfficeAssistantController::class, 'destroyConversation'])->name('ai.assistant.conversations.destroy');

    // ============================================
    // KNOWLEDGE BASE (AI / RAG) — Admin only
    // ============================================
    Route::middleware('permission:manage-admin')->prefix('knowledge-base')->name('knowledge-base.')->group(function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
        Route::post('/sync', [KnowledgeBaseController::class, 'sync'])->name('sync');
        Route::delete('/{document}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
    });

    // ============================================
    // PRODUCT KNOWLEDGE BASE (AI / RAG) — Admin only
    // ============================================
    Route::middleware('permission:manage-admin')->prefix('product-knowledge')->name('product-knowledge.')->group(function () {
        Route::get('/', [ProductKnowledgeController::class, 'index'])->name('index');
        Route::post('/import', [ProductKnowledgeController::class, 'import'])->name('import');
        Route::delete('/products/bulk', [ProductKnowledgeController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('/products/{product}', [ProductKnowledgeController::class, 'show'])->name('show');
        Route::patch('/products/{product}', [ProductKnowledgeController::class, 'update'])->name('update');
        Route::patch('/products/{product}/publish', [ProductKnowledgeController::class, 'publish'])->name('publish');
        Route::delete('/sources/{source}', [ProductKnowledgeController::class, 'destroySource'])->name('sources.destroy');
        Route::patch('/products/{product}/reject', [ProductKnowledgeController::class, 'reject'])->name('reject');
        Route::post('/sources/{source}/refetch', [ProductKnowledgeController::class, 'refetch'])->name('refetch');
    });

    // ============================================
    // SALES — manage
    // ============================================
    Route::middleware('permission:manage-sales')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('/customers/{customer}/contacts/create', [CustomerContactController::class, 'create'])->name('customer-contacts.create');
        Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customer-contacts.store');
        Route::get('/customer-contacts/{customerContact}/edit', [CustomerContactController::class, 'edit'])->name('customer-contacts.edit');
        Route::put('/customer-contacts/{customerContact}', [CustomerContactController::class, 'update'])->name('customer-contacts.update');
        Route::delete('/customer-contacts/{customerContact}', [CustomerContactController::class, 'destroy'])->name('customer-contacts.destroy');
        Route::patch('/customer-contacts/{customerContact}/primary', [CustomerContactController::class, 'togglePrimary'])->name('customer-contacts.primary');
    });

    // Upload file langsung ke customer (boleh tanpa project).
    // Izin rinci dicek di controller: teknisi ATAU pengedit customer.
    Route::middleware('permission:manage-technician|manage-sales')->group(function () {
        Route::post('/customers/{customer}/documents', [ProjectDocumentController::class, 'storeForCustomer'])->name('customer-documents.store');
    });

    Route::middleware('permission:view-sales|manage-sales|view-customer')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/npwp', [CustomerController::class, 'downloadNpwp'])->name('customers.npwp');
    });

    // ============================================
    // SALES — Follow Up
    // ============================================
    Route::middleware('permission:manage-sales')->prefix('sales')->name('sales.')->group(function () {
        Route::resource('follow-ups', FollowUpController::class)->except(['show']);
        Route::get('follow-ups/{followUp}', [FollowUpController::class, 'show'])->name('follow-ups.show');

        Route::resource('pocs', PocController::class)->except(['show']);
        Route::get('pocs/{poc}', [PocController::class, 'show'])->name('pocs.show');
        Route::post('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
        Route::post('follow-ups/{followUp}/reopen', [FollowUpController::class, 'reopen'])->name('follow-ups.reopen');
        Route::post('follow-ups/{followUp}/snooze', [FollowUpController::class, 'snooze'])->name('follow-ups.snooze');

        // Follow Up create with optional pre-selected customer
        Route::get('follow-ups/create/{customer?}', [FollowUpController::class, 'create'])->name('follow-ups.create-with-customer');
    });

    // ============================================
    // SALES FLOW — Jadwal Sales, Technical Request, Proposal
    // Tulis: sales & inside-sales. Aksi teknisi di blok manage-technician.
    // ============================================
    Route::middleware('permission:manage-sales|manage-inside-sales')->prefix('sales')->name('sales.')->group(function () {
        Route::get('schedules/create', [SalesScheduleController::class, 'create'])->name('schedules.create');
        Route::post('schedules', [SalesScheduleController::class, 'store'])->name('schedules.store');
        Route::get('schedules/{schedule}/edit', [SalesScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('schedules/{schedule}', [SalesScheduleController::class, 'update'])->name('schedules.update');
        Route::post('schedules/{schedule}/complete', [SalesScheduleController::class, 'complete'])->name('schedules.complete');
        Route::post('schedules/{schedule}/cancel', [SalesScheduleController::class, 'cancel'])->name('schedules.cancel');

        Route::get('technical-requests/create', [TechnicalRequestController::class, 'create'])->name('technical-requests.create');
        Route::post('technical-requests', [TechnicalRequestController::class, 'store'])->name('technical-requests.store');
        Route::post('technical-requests/{technicalRequest}/cancel', [TechnicalRequestController::class, 'cancel'])->name('technical-requests.cancel');

        Route::get('proposals/create', [ProposalController::class, 'create'])->name('proposals.create');
        Route::post('proposals', [ProposalController::class, 'store'])->name('proposals.store');
        Route::get('proposals/{proposal}/edit', [ProposalController::class, 'edit'])->name('proposals.edit');
        Route::put('proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
        Route::post('proposals/{proposal}/ready', [ProposalController::class, 'markReady'])->name('proposals.ready');
        Route::post('proposals/{proposal}/send', [ProposalController::class, 'send'])->name('proposals.send');
        Route::post('proposals/{proposal}/viewed', [ProposalController::class, 'markViewed'])->name('proposals.viewed');
        Route::post('proposals/{proposal}/revise', [ProposalController::class, 'revise'])->name('proposals.revise');
        Route::post('proposals/{proposal}/respond', [ProposalController::class, 'respond'])->name('proposals.respond');
        Route::post('proposals/{proposal}/cancel', [ProposalController::class, 'cancel'])->name('proposals.cancel');
        Route::post('proposals/{proposal}/submit-technical', [ProposalController::class, 'submitTechnical'])->name('proposals.submit-technical');
        Route::post('proposals/{proposal}/po', [ProposalController::class, 'uploadPo'])->name('proposals.upload-po');
        Route::post('proposals/{proposal}/convert', [ProposalController::class, 'convertToProject'])->name('proposals.convert');
    });

    Route::middleware('permission:manage-technician|manage-admin')->prefix('sales')->name('sales.')->group(function () {
        Route::post('proposals/{proposal}/return-technical', [ProposalController::class, 'returnTechnical'])->name('proposals.return-technical');
        Route::post('proposals/{proposal}/complete-technical', [ProposalController::class, 'completeTechnical'])->name('proposals.complete-technical');
    });

    // Price Request dipakai bersama sales + teknisi (satu tabel, terhubung):
    // form & file sama persis, teknisi juga bisa membuat.
    Route::middleware('permission:manage-sales|manage-inside-sales|manage-technician')->prefix('sales')->name('sales.')->group(function () {
        Route::get('price-requests/create', [PriceRequestController::class, 'create'])->name('price-requests.create');
        Route::post('price-requests', [PriceRequestController::class, 'store'])->name('price-requests.store');
        Route::post('price-requests/{priceRequest}/cancel', [PriceRequestController::class, 'cancel'])->name('price-requests.cancel');
    });

    // ============================================
    // MASTER PRODUK (katalog BOQ proposal)
    // ============================================
    Route::middleware('permission:manage-sales|manage-admin')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    Route::middleware('permission:view-sales|manage-sales|view-admin|manage-admin')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/picker', [ProductController::class, 'picker'])->name('products.picker');
    });

    Route::middleware('permission:view-sales|manage-sales|manage-inside-sales|manage-technician')->prefix('sales')->name('sales.')->group(function () {
        Route::get('requests', [SalesRequestHubController::class, 'index'])->name('requests.index');
        Route::get('schedules', [SalesScheduleController::class, 'index'])->name('schedules.index');
        Route::get('schedules/api', [SalesScheduleController::class, 'getSchedulesApi'])->name('schedules.api');
        Route::get('schedules/{schedule}', [SalesScheduleController::class, 'show'])->name('schedules.show');
        Route::get('technical-requests/{technicalRequest}', [TechnicalRequestController::class, 'show'])->name('technical-requests.show');
        Route::get('technical-requests/{technicalRequest}/attachment', [TechnicalRequestController::class, 'downloadAttachment'])->name('technical-requests.attachment');
        Route::get('price-requests/{priceRequest}', [PriceRequestController::class, 'show'])->name('price-requests.show');
        Route::get('price-requests/{priceRequest}/quotation', [PriceRequestController::class, 'downloadQuotation'])->name('price-requests.quotation');
        Route::get('price-requests/{priceRequest}/reply', [PriceRequestController::class, 'downloadReply'])->name('price-requests.reply');
        Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
        Route::get('proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
        Route::get('proposals/{proposal}/preview', [ProposalController::class, 'preview'])->name('proposals.preview');
        Route::get('proposals/{proposal}/po', [ProposalController::class, 'downloadPo'])->name('proposals.download-po');
    });

    Route::middleware('permission:manage-technician')->prefix('sales')->name('sales.')->group(function () {
        Route::post('technical-requests/{technicalRequest}/review', [TechnicalRequestController::class, 'review'])->name('technical-requests.review');
        Route::post('technical-requests/{technicalRequest}/assign', [TechnicalRequestController::class, 'assign'])->name('technical-requests.assign');
        Route::post('technical-requests/{technicalRequest}/progress', [TechnicalRequestController::class, 'progress'])->name('technical-requests.progress');
        Route::post('technical-requests/{technicalRequest}/result', [TechnicalRequestController::class, 'result'])->name('technical-requests.result');
        Route::post('technical-requests/{technicalRequest}/complete', [TechnicalRequestController::class, 'complete'])->name('technical-requests.complete');

        Route::post('price-requests/{priceRequest}/take', [PriceRequestController::class, 'take'])->name('price-requests.take');
        Route::post('price-requests/{priceRequest}/reject', [PriceRequestController::class, 'reject'])->name('price-requests.reject');
        Route::post('price-requests/{priceRequest}/review', [PriceRequestController::class, 'review'])->name('price-requests.review');
        Route::post('price-requests/{priceRequest}/assign', [PriceRequestController::class, 'assign'])->name('price-requests.assign');
        Route::post('price-requests/{priceRequest}/reply', [PriceRequestController::class, 'reply'])->name('price-requests.reply.store');
    });

    Route::middleware('permission:view-sales|manage-sales')->prefix('sales')->name('sales.')->group(function () {
        Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('pocs', [PocController::class, 'index'])->name('pocs.index');
    });

    // Tambah/ubah/hapus project: teknisi ATAU sales untuk customer yang
    // boleh ia edit (cek customer di policy/controller). Daftar project
    // dan halaman Kelola Dokumen tetap ikut aturan masing-masing.
    Route::middleware('permission:manage-technician|manage-sales')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    });

    // ============================================
    // TEKNISI — manage
    // ============================================
    Route::middleware('permission:manage-technician')->group(function () {
        Route::get('/projects/{project}/supports/create', [ProjectSupportController::class, 'create'])->name('project-supports.create');
        Route::post('/projects/{project}/supports', [ProjectSupportController::class, 'store'])->name('project-supports.store');
        Route::delete('/project-supports/{projectSupport}', [ProjectSupportController::class, 'destroy'])->name('project-supports.destroy');

        Route::get('/projects/{project}/tasks/create', [ProjectTaskController::class, 'create'])->name('project-tasks.create');
        Route::post('/projects/{project}/tasks', [ProjectTaskController::class, 'store'])->name('project-tasks.store');
        Route::delete('/project-tasks/{projectTask}', [ProjectTaskController::class, 'destroy'])->name('project-tasks.destroy');

        Route::delete('/project-documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('project-documents.destroy');
    });

    // Upload dokumen proyek: teknisi ATAU sales yang boleh edit customer-nya
    // (cek ganda di controller). Hapus tetap khusus teknisi.
    Route::middleware('permission:manage-technician|manage-sales')->group(function () {
        Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('project-documents.store');
        Route::patch('/project-documents/{document}/rename', [ProjectDocumentController::class, 'rename'])->name('project-documents.rename');
    });

    Route::middleware('permission:view-technician|manage-technician|view-sales|manage-sales')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'index'])->name('project-documents.index');
        Route::get('/project-documents/{document}/download', [ProjectDocumentController::class, 'download'])->name('project-documents.download');
        Route::get('/project-documents/{document}/preview', [ProjectDocumentController::class, 'preview'])->name('project-documents.preview');
    });

    // ============================================
    // TEKNISI — Kalender & Jadwal (Google Calendar)
    // ============================================
    Route::middleware('permission:view-technician|manage-technician')->prefix('teknisi')->name('teknisi.')->group(function () {
        Route::get('/dashboard', [TechnicianDashboardController::class, 'index'])->name('dashboard');
        Route::get('/kalender/events', [TechnicianScheduleController::class, 'events'])->name('kalender.events');
        Route::get('/kalender/status', [TechnicianScheduleController::class, 'googleStatus'])->name('kalender.status');
        Route::get('/schedules/api', [TechnicianScheduleController::class, 'getSchedulesApi'])->name('schedules.api');
        Route::get('/jadwal/google-events', [CalendarController::class, 'googleEvents'])->name('google-events');
        Route::get('/jadwal', [TechnicianScheduleController::class, 'jadwal'])->name('jadwal');
    });

    Route::middleware('permission:manage-technician')->prefix('teknisi')->name('teknisi.')->group(function () {
        Route::post('/schedules', [TechnicianScheduleController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{schedule}', [TechnicianScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{schedule}', [TechnicianScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::post('/kalender/credentials', [TechnicianScheduleController::class, 'saveCredentials'])->name('kalender.credentials');
        Route::post('/kalender/sync', [TechnicianScheduleController::class, 'syncNow'])->name('kalender.sync');
        Route::get('/kalender/connect', [TechnicianScheduleController::class, 'connect'])->name('kalender.connect');
        Route::get('/kalender/callback', [TechnicianScheduleController::class, 'callback'])->name('kalender.callback');
        Route::delete('/kalender/disconnect', [TechnicianScheduleController::class, 'disconnect'])->name('kalender.disconnect');
    });

    // ============================================
    // TEKNISI — Technical Workflow (Survey, Sizing, Request Harga, Instalasi, Document)
    // ============================================
    Route::middleware('permission:view-technician|manage-technician')->prefix('teknisi')->name('teknisi.')->group(function () {
        // Inbox permintaan masuk dari sales (Request Harga + Request Tim Engineer).
        Route::get('/requests', [TeknisiRequestController::class, 'index'])->name('requests.index');
        // Detail request dalam konteks Teknisi: notifikasi sales→teknisi mendarat
        // di menu Teknisi (sidebar Teknisi aktif), bukan di menu Sales.
        Route::get('/price-requests/{priceRequest}', [PriceRequestController::class, 'show'])->name('price-requests.show');
        Route::get('/technical-requests/{technicalRequest}', [TechnicalRequestController::class, 'show'])->name('technical-requests.show');
        Route::get('/surveys/create', [\App\Http\Controllers\SurveyController::class, 'create'])->name('surveys.create');
        Route::get('/sizing-projects', [\App\Http\Controllers\SizingProjectController::class, 'index'])->name('sizing-projects.index');
        Route::get('/sizing-projects/create', [\App\Http\Controllers\SizingProjectController::class, 'create'])->name('sizing-projects.create');
        Route::get('/sizing-projects/{sizingProject}', [\App\Http\Controllers\SizingProjectController::class, 'show'])->name('sizing-projects.show');
        Route::get('/request-hargas', [\App\Http\Controllers\RequestHargaController::class, 'index'])->name('request-hargas.index');
        Route::get('/request-hargas/create', [\App\Http\Controllers\RequestHargaController::class, 'create'])->name('request-hargas.create');
        Route::get('/request-hargas/{requestHarga}', [\App\Http\Controllers\RequestHargaController::class, 'show'])->name('request-hargas.show');
        Route::get('/instalasis', [\App\Http\Controllers\InstalasiController::class, 'index'])->name('instalasis.index');
        Route::get('/instalasis/create', [\App\Http\Controllers\InstalasiController::class, 'create'])->name('instalasis.create');
        Route::get('/instalasis/{instalasi}', [\App\Http\Controllers\InstalasiController::class, 'show'])->name('instalasis.show');
        Route::get('/instalasis/{instalasi}/report', [\App\Http\Controllers\InstalasiController::class, 'viewReport'])->name('instalasis.report.view');
        Route::get('/instalasis/{instalasi}/report/download', [\App\Http\Controllers\InstalasiController::class, 'downloadReport'])->name('instalasis.report.download');
        Route::get('/documents', [\App\Http\Controllers\DocumentRepositoryController::class, 'index'])->name('documents.index');
    });

    // Baca Survey dibuka untuk Sales (dan sales-pt) yang memegang project tsb
    // (read-only; kepemilikan dijaga di controller). Tulis tetap manage-technician.
    Route::middleware('permission:view-technician|manage-technician|view-sales')->prefix('teknisi')->name('teknisi.')->group(function () {
        Route::get('/surveys', [\App\Http\Controllers\SurveyController::class, 'index'])->name('surveys.index');
        Route::get('/surveys/{survey}', [\App\Http\Controllers\SurveyController::class, 'show'])->name('surveys.show');
        Route::get('/surveys/{survey}/report', [\App\Http\Controllers\SurveyController::class, 'viewReport'])->name('surveys.report.view');
        Route::get('/surveys/{survey}/report/download', [\App\Http\Controllers\SurveyController::class, 'downloadReport'])->name('surveys.report.download');
    });

    Route::middleware('permission:manage-technician')->prefix('teknisi')->name('teknisi.')->group(function () {
        Route::post('/surveys', [\App\Http\Controllers\SurveyController::class, 'store'])->name('surveys.store');
        Route::post('/surveys/{survey}/report', [\App\Http\Controllers\SurveyController::class, 'uploadReport'])->name('surveys.report.upload');
        Route::put('/surveys/{survey}', [\App\Http\Controllers\SurveyController::class, 'update'])->name('surveys.update');
        Route::delete('/surveys/{survey}', [\App\Http\Controllers\SurveyController::class, 'destroy'])->name('surveys.destroy');
        Route::get('/surveys/{survey}/edit', [\App\Http\Controllers\SurveyController::class, 'edit'])->name('surveys.edit');
        Route::post('/sizing-projects', [\App\Http\Controllers\SizingProjectController::class, 'store'])->name('sizing-projects.store');
        Route::put('/sizing-projects/{sizingProject}', [\App\Http\Controllers\SizingProjectController::class, 'update'])->name('sizing-projects.update');
        Route::delete('/sizing-projects/{sizingProject}', [\App\Http\Controllers\SizingProjectController::class, 'destroy'])->name('sizing-projects.destroy');
        Route::get('/sizing-projects/{sizingProject}/edit', [\App\Http\Controllers\SizingProjectController::class, 'edit'])->name('sizing-projects.edit');
        Route::post('/request-hargas', [\App\Http\Controllers\RequestHargaController::class, 'store'])->name('request-hargas.store');
        Route::put('/request-hargas/{requestHarga}', [\App\Http\Controllers\RequestHargaController::class, 'update'])->name('request-hargas.update');
        Route::delete('/request-hargas/{requestHarga}', [\App\Http\Controllers\RequestHargaController::class, 'destroy'])->name('request-hargas.destroy');
        Route::get('/request-hargas/{requestHarga}/edit', [\App\Http\Controllers\RequestHargaController::class, 'edit'])->name('request-hargas.edit');
        Route::post('/instalasis', [\App\Http\Controllers\InstalasiController::class, 'store'])->name('instalasis.store');
        Route::put('/instalasis/{instalasi}', [\App\Http\Controllers\InstalasiController::class, 'update'])->name('instalasis.update');
        Route::delete('/instalasis/{instalasi}', [\App\Http\Controllers\InstalasiController::class, 'destroy'])->name('instalasis.destroy');
        Route::get('/instalasis/{instalasi}/edit', [\App\Http\Controllers\InstalasiController::class, 'edit'])->name('instalasis.edit');
    });

    // ============================================
    // ADMIN — Trash
    // ============================================
    // Modul Trash: gate view-trash, kepemilikan dijaga Policy di backend.
    Route::middleware('permission:view-trash')->group(function () {
        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::patch('/trash/customers/{id}/restore', [TrashController::class, 'restoreCustomer'])->name('trash.restore-customer');
        Route::patch('/trash/projects/{id}/restore', [TrashController::class, 'restoreProject'])->name('trash.restore-project');
        Route::delete('/trash/customers/{id}/delete', [TrashController::class, 'destroyCustomer'])->name('trash.destroy-customer');
        Route::delete('/trash/projects/{id}/delete', [TrashController::class, 'destroyProject'])->name('trash.destroy-project');
        Route::delete('/trash/clear', [TrashController::class, 'clear'])->name('trash.clear');
    });

    // ============================================
    // ADMIN — Invoice, PO, Payment
    // ============================================
    Route::middleware('permission:manage-admin')->prefix('admin')->name('admin.')->group(function () {
        // Invoice — data dibuat via upload file di halaman customer
        Route::get('/invoices/{invoice}/edit', [AdminController::class, 'invoicesEdit'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [AdminController::class, 'invoicesUpdate'])->name('invoices.update');
        Route::delete('/invoices/{invoice}', [AdminController::class, 'invoicesDestroy'])->name('invoices.destroy');

        // PO — data dibuat via upload file di halaman customer
        Route::get('/pos/{purchaseOrder}/edit', [AdminController::class, 'posEdit'])->name('pos.edit');
        Route::put('/pos/{purchaseOrder}', [AdminController::class, 'posUpdate'])->name('pos.update');
        Route::delete('/pos/{purchaseOrder}', [AdminController::class, 'posDestroy'])->name('pos.destroy');

        // Payment — data dibuat via upload file di halaman customer
        // Bukti transfer memuat nomor rekening — hanya manage-admin (bukan view-only).
        Route::get('/payments/{payment}/proof', [AdminController::class, 'paymentsProof'])->name('payments.proof');
    });

    Route::middleware('permission:view-admin|manage-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/invoices', [AdminController::class, 'invoicesIndex'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [AdminController::class, 'invoicesShow'])->name('invoices.show');
        Route::get('/pos', [AdminController::class, 'posIndex'])->name('pos.index');
        Route::get('/pos/{purchaseOrder}', [AdminController::class, 'posShow'])->name('pos.show');
        Route::get('/payments', [AdminController::class, 'paymentsIndex'])->name('payments.index');
        Route::get('/payments/{payment}', [AdminController::class, 'paymentsShow'])->name('payments.show');
    });

    // ============================================
    // MARKETING — manage
    // ============================================
    Route::middleware('permission:manage-marketing')->group(function () {
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::get('/leads/import', [LeadController::class, 'importForm'])->name('leads.import');
        // Parse xlsx di memori: throttle agar tidak di-DoS.
        Route::post('/leads/import', [LeadController::class, 'import'])
            ->middleware('throttle:6,1')
            ->name('leads.import.execute');

        // Data Partner (supplier, vendor, kontraktor, partner, distributor)
        Route::get('/partners/create', [PartnerController::class, 'create'])->name('partners.create');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::get('/partners/{partner}/edit', [PartnerController::class, 'edit'])->name('partners.edit');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');
    });

    // Convert & status lead — dipakai Sales (own lead) + fallback Teknisi/Admin,
    // otorisasi detail (owner vs semua) dicek di controller.
    Route::middleware('permission:manage-marketing|manage-sales|manage-technician|manage-admin')->group(function () {
        Route::patch('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status');
        Route::patch('/leads/batch-status', [LeadController::class, 'batchUpdateStatus'])->name('leads.batch-status');
        Route::patch('/leads/{lead}/outcome', [LeadController::class, 'saveOutcome'])->name('leads.outcome');
    });
    // Monitoring tim marketing — khusus lead divisi (sebelum /leads/{lead})
    Route::get('/leads/monitoring', [LeadController::class, 'monitoring'])
        ->middleware('permission:monitor-marketing')
        ->name('leads.monitoring');

    // Pipeline khusus Sales, selalu terkunci ke lead milik viewer (1 sales = 1 tampilan).
    Route::middleware('permission:view-sales|manage-sales')->group(function () {
        Route::get('/leads/pipeline', [LeadController::class, 'pipeline'])->name('leads.pipeline');
    });

    Route::middleware('permission:view-marketing|manage-marketing')->group(function () {
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/marketing/dashboard', [LeadController::class, 'dashboard'])->name('marketing.dashboard');
        Route::get('/leads/activities', [LeadController::class, 'activities'])->name('leads.activities');
        Route::get('/leads/{lead}/documents/{document}/preview', [LeadController::class, 'previewDocument'])->name('leads.documents.preview');
        Route::get('/leads/{lead}/documents/{document}/download', [LeadController::class, 'downloadDocument'])->name('leads.documents.download');

        // Lampiran lead (BOQ awal / kebutuhan user)
        Route::get('/leads/{lead}/attachments/{document}', [LeadController::class, 'showAttachment'])->name('leads.attachments.show');
        Route::get('/leads/{lead}/attachments/{document}/download', [LeadController::class, 'downloadAttachment'])->name('leads.attachments.download');
        Route::delete('/leads/{lead}/attachments/{document}', [LeadController::class, 'destroyAttachment'])
            ->middleware('permission:manage-marketing')
            ->name('leads.attachments.destroy');
        Route::patch('/leads/{lead}/attachments/{document}/category', [LeadController::class, 'categorizeAttachment'])
            ->middleware('permission:manage-marketing')
            ->name('leads.attachments.category');

        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    });

    // Detail lead — HARUS di bawah semua /leads/* spesifik agar tidak memangsa
    // /leads/activities dkk (route {lead} menelan segmen apa pun). Akses luas:
    // Marketing & Sales (+ teknisi/admin), sales di-scope ke own lead di policy.
    Route::get('/leads/{lead}', [LeadController::class, 'show'])
        ->middleware('permission:view-marketing|manage-marketing|view-sales|manage-sales|manage-technician|manage-admin|manage-inside-sales')
        ->name('leads.show');

    // ============================================
    // WHATSAPP CENTER MARKETING (inbox 4 WA Company)
    // ============================================
    Route::middleware('permission:view-marketing|manage-marketing')->prefix('whatsapp-center')->name('whatsapp-center.')->group(function () {
        Route::get('/', [WhatsAppCenterController::class, 'index'])->name('index');
        Route::get('/status', [WhatsAppCenterController::class, 'status'])->name('status');
        Route::get('/{account}/conversations', [WhatsAppCenterController::class, 'conversations'])->name('conversations');
        Route::get('/{account}/contacts', [WhatsAppCenterController::class, 'contacts'])->name('contacts');
        Route::get('/{account}/messages/{sender}', [WhatsAppCenterController::class, 'messages'])->name('messages');
        Route::get('/{account}/check-status', [WhatsAppCenterController::class, 'checkStatus'])->name('check-status');
        Route::post('/{account}/messages/{sender}/read', [WhatsAppCenterController::class, 'markRead'])->name('mark-read');
        Route::post('/{account}/conversations/{sender}/preference', [WhatsAppCenterController::class, 'preference'])->name('preference');

        Route::middleware('permission:manage-marketing')->group(function () {
            Route::post('/{account}/messages/{sender}', [WhatsAppCenterController::class, 'store'])->name('reply');
            Route::post('/{account}/conversations/{sender}/takeover', [WhatsAppCenterController::class, 'takeover'])->name('takeover');
            Route::post('/{account}/conversations/{sender}/release', [WhatsAppCenterController::class, 'release'])->name('release');
            Route::post('/{account}/convert/{sender}', [WhatsAppCenterController::class, 'convert'])->name('convert');
            Route::post('/{account}/contacts', [WhatsAppCenterController::class, 'saveContact'])->name('contact-save');
            Route::post('/{account}/simulate', [WhatsAppCenterController::class, 'simulate'])->name('simulate');
            Route::put('/{account}/credentials', [WhatsAppCenterController::class, 'updateCredentials'])->name('credentials');
            Route::put('/{account}/bot-settings', [WhatsAppCenterController::class, 'updateBotSettings'])->name('bot-settings');
        });
    });

    // ============================================
    // MANAGE SALES — Management (assign lead ke sales)
    // ============================================
    Route::middleware('permission:manage-sales-leads')->prefix('manage-sales')->name('manage-sales.')->group(function () {
        Route::get('/', [ManageSalesController::class, 'index'])->name('index');
        Route::get('/activity-log', [ManageSalesController::class, 'activityLog'])->name('activity-log');
        Route::get('/{lead}/edit', [ManageSalesController::class, 'edit'])->name('edit');
        Route::put('/{lead}', [ManageSalesController::class, 'update'])->name('update');
        Route::post('/{lead}/assign', [ManageSalesController::class, 'assign'])->name('assign');
    });

    // ============================================
    // MANAGEMENT — sub-modul baru (under development)
    // ============================================
    Route::middleware('permission:manage-sales-leads')->prefix('manage')->name('manage.')->group(function () {
        Route::view('marketing', 'manage.placeholder', ['title' => 'Manage Marketing'])->name('marketing.index');
        Route::view('technical', 'manage.placeholder', ['title' => 'Manage Technical'])->name('technical.index');
        Route::view('admin', 'manage.placeholder', ['title' => 'Manage Admin'])->name('admin.index');
    });

    // Lead milik sales (muncul di menu Sales)
    Route::middleware('permission:view-sales|manage-sales')->get('/sales/dashboard', [ManageSalesController::class, 'dashboard'])->name('sales.dashboard');
    Route::middleware('permission:view-sales|manage-sales')->get('/sales/my-leads/export', [ManageSalesController::class, 'exportMyLeads'])->name('sales.my-leads.export');
    Route::middleware('permission:view-sales|manage-sales')->get('/sales/my-leads', [ManageSalesController::class, 'myLeads'])->name('sales.my-leads');

    // ============================================
    // INSIDE SALES TASK — request bantuan untuk lead
    // ============================================
    Route::middleware('permission:view-sales|manage-sales|manage-sales-leads|manage-inside-sales')
        ->prefix('lead-tasks')->name('lead-tasks.')->group(function () {
            Route::get('/', [LeadTaskController::class, 'index'])->name('index');
            Route::get('/create', [LeadTaskController::class, 'create'])->name('create');
            Route::post('/', [LeadTaskController::class, 'store'])->name('store');
            Route::get('/{leadTask}', [LeadTaskController::class, 'show'])->name('show');
            Route::get('/{leadTask}/edit', [LeadTaskController::class, 'edit'])->name('edit');
            Route::put('/{leadTask}', [LeadTaskController::class, 'update'])->name('update');
            Route::delete('/{leadTask}', [LeadTaskController::class, 'destroy'])->name('destroy');
            Route::post('/{leadTask}/comments', [LeadTaskController::class, 'storeComment'])->name('comments.store');
        });

    // ============================================
    // MONITORING — Manager & Super Admin
    // ============================================
    Route::get('/monitoring',
        [MonitoringController::class, 'index']
    )->middleware(['auth', 'verified', 'permission:view-monitoring|manage-monitoring'])->name('monitoring.index');

    // ============================================
    // ADMIN PANEL — Super Admin only (settings, config)
    // ============================================
    Route::middleware('permission:manage-monitoring')->prefix('admin-panel')->name('admin-panel.')->group(function () {
        Route::get('/', [AdminPanelController::class, 'index'])->name('index');

        // Users
        Route::get('/users/create', [AdminPanelController::class, 'createUser'])->name('users.create');
        Route::post('/users', [AdminPanelController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminPanelController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}', [AdminPanelController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminPanelController::class, 'destroyUser'])->name('users.destroy');

        // Roles
        Route::get('/roles/create', [AdminPanelController::class, 'createRole'])->name('roles.create');
        Route::post('/roles', [AdminPanelController::class, 'storeRole'])->name('roles.store');
        Route::get('/roles/{role}/edit', [AdminPanelController::class, 'editRole'])->name('roles.edit');
        Route::put('/roles/{role}', [AdminPanelController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [AdminPanelController::class, 'destroyRole'])->name('roles.destroy');

        // Audit Log
        Route::get('/audit-log', [AdminPanelController::class, 'auditLog'])->name('audit-log');

        // Account Manager
        Route::get('/account-managers', [AccountManagerController::class, 'index'])->name('account-managers.index');
        Route::resource('account-managers', AccountManagerController::class)->except(['index']);

        // Work Type
        Route::get('/work-types', [WorkTypeController::class, 'index'])->name('work-types.index');
        Route::resource('work-types', WorkTypeController::class)->except(['index']);

        // Document Categories
        Route::get('/document-categories', [DocumentCategoryController::class, 'index'])->name('document-categories.index');
        Route::get('/document-categories/create', [DocumentCategoryController::class, 'create'])->name('document-categories.create');
        Route::post('/document-categories', [DocumentCategoryController::class, 'store'])->name('document-categories.store');
        Route::get('/document-categories/{documentCategory}/edit', [DocumentCategoryController::class, 'edit'])->name('document-categories.edit');
        Route::put('/document-categories/{documentCategory}', [DocumentCategoryController::class, 'update'])->name('document-categories.update');
        Route::delete('/document-categories/{documentCategory}', [DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');
        Route::patch('/document-categories/{id}/restore', [DocumentCategoryController::class, 'restore'])->name('document-categories.restore');

        // Project Statuses
        Route::get('/project-statuses', ProjectStatusList::class)->name('project-statuses.index');
        Route::get('/project-statuses/create', [ProjectStatusController::class, 'create'])->name('project-statuses.create');
        Route::post('/project-statuses', [ProjectStatusController::class, 'store'])->name('project-statuses.store');
        Route::get('/project-statuses/{projectStatus}/edit', [ProjectStatusController::class, 'edit'])->name('project-statuses.edit');
        Route::put('/project-statuses/{projectStatus}', [ProjectStatusController::class, 'update'])->name('project-statuses.update');
        Route::delete('/project-statuses/{projectStatus}', [ProjectStatusController::class, 'destroy'])->name('project-statuses.destroy');
    });
});
require __DIR__.'/auth.php';

// Webhook gateway WhatsApp (dipanggil provider/gateway). CSRF dikecualikan
// di bootstrap/app.php lewat validateCsrfTokens(except: ['api/whatsapp/webhook']).
Route::get('/api/whatsapp/webhook', [WhatsAppCenterController::class, 'verifyWebhook']);
Route::post('/api/whatsapp/webhook', [WhatsAppCenterController::class, 'webhook'])
    ->middleware('throttle:60,1');
