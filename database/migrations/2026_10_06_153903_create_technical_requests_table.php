<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 32)->default('survey');
            $table->string('title');
            $table->text('requirement')->nullable();
            $table->text('problem_description')->nullable();
            $table->text('scope')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->date('target_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('waiting_lead');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('technical_result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lead_id', 'status']);
            $table->index(['assigned_technician_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_requests');
    }
};
