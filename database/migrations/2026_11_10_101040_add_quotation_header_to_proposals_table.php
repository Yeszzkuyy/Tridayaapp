<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('proposals', 'pdf_path')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            $table->text('customer_address')->nullable()->after('customer');
            $table->string('contact_name')->nullable()->after('customer_address');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 50)->nullable()->after('contact_email');
            $table->text('project_description')->nullable()->after('contact_phone');
            $table->string('payment_terms_text')->nullable()->after('project_description');
            $table->string('letterhead_pt', 10)->nullable()->after('payment_terms_text');
            $table->string('pdf_path')->nullable()->after('letterhead_pt');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proposals', 'pdf_path')) {
            return;
        }

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn([
                'customer_address',
                'contact_name',
                'contact_email',
                'contact_phone',
                'project_description',
                'payment_terms_text',
                'letterhead_pt',
                'pdf_path',
                'pdf_generated_at',
            ]);
        });
    }
};
