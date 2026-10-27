<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen boleh nempel ke customer langsung (tanpa project):
     * project_id jadi nullable + tambah customer_id.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::create('project_documents_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->text('notes')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });

            $cols = ['id', 'project_id', 'file_name', 'file_path', 'notes', 'uploaded_by', 'document_category_id', 'category_id', 'created_at', 'updated_at', 'deleted_at'];
            DB::table('project_documents_new')->insertUsing(
                $cols,
                DB::table('project_documents')->select($cols)
            );

            Schema::drop('project_documents');
            Schema::rename('project_documents_new', 'project_documents');

            return;
        }

        DB::statement('ALTER TABLE project_documents ALTER COLUMN project_id DROP NOT NULL');

        Schema::table('project_documents', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('project_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::create('project_documents_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->text('notes')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });

            $cols = ['id', 'project_id', 'file_name', 'file_path', 'notes', 'uploaded_by', 'document_category_id', 'category_id', 'created_at', 'updated_at', 'deleted_at'];
            DB::table('project_documents_old')->insertUsing(
                $cols,
                DB::table('project_documents')->select($cols)->whereNotNull('project_id')
            );

            Schema::drop('project_documents');
            Schema::rename('project_documents_old', 'project_documents');

            return;
        }

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        DB::statement('ALTER TABLE project_documents ALTER COLUMN project_id SET NOT NULL');
    }
};
