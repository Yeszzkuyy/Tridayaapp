<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fondasi integrasi Engineer:
     * - identitas engineer memakai akun user (bukan hanya nama teks),
     * - pekerjaan lapangan bisa berdiri sendiri (non-project),
     * - BAST instalasi non-project punya kolom sendiri,
     * - workflow project menyimpan state langkah terakhir.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('workflow_state')->nullable()->after('progress');
            $table->timestamp('workflow_synced_at')->nullable()->after('workflow_state');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('technician_user_id')
                ->nullable()
                ->after('sales_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('technician_user_id');
        });

        Schema::table('sizing_projects', function (Blueprint $table) {
            $table->foreignId('technician_user_id')
                ->nullable()
                ->after('project_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('technician_user_id');
        });

        Schema::table('technical_requests', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('projects')
                ->nullOnDelete();
            $table->index('project_id');
        });

        // Instalasi boleh berdiri sendiri untuk pekerjaan non-project.
        Schema::table('instalasis', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('project_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('sales_user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('technical_request_id')->nullable()->after('sales_user_id')->constrained('technical_requests')->nullOnDelete();
            $table->foreignId('technician_user_id')->nullable()->after('technician_pic')->constrained('users')->nullOnDelete();

            // BAST untuk instalasi (wajib sebelum pekerjaan dianggap selesai).
            $table->string('bast_number')->nullable();
            $table->date('bast_date')->nullable();
            $table->foreignId('bast_document_id')->nullable()->constrained('project_documents')->nullOnDelete();
            $table->string('bast_signed_3dy_name')->nullable();
            $table->timestamp('bast_signed_3dy_at')->nullable();
            $table->string('bast_signed_customer_name')->nullable();
            $table->timestamp('bast_signed_customer_at')->nullable();
            $table->timestamp('bast_ready_at')->nullable();

            $table->unique('technical_request_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('instalasis', function (Blueprint $table) {
            $table->dropUnique(['technical_request_id']);
            $table->dropForeign(['technical_request_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['sales_user_id']);
            $table->dropForeign(['technician_user_id']);
            $table->dropForeign(['bast_document_id']);
            $table->dropColumn([
                'customer_id',
                'sales_user_id',
                'technical_request_id',
                'technician_user_id',
                'bast_number',
                'bast_date',
                'bast_document_id',
                'bast_signed_3dy_name',
                'bast_signed_3dy_at',
                'bast_signed_customer_name',
                'bast_signed_customer_at',
                'bast_ready_at',
            ]);
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('technical_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('sizing_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_user_id');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_user_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['workflow_state', 'workflow_synced_at']);
        });
    }
};
