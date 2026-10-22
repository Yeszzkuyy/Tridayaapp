<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_request_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->text('notes')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['price_request_id', 'version']);
        });

        DB::table('price_requests')
            ->whereNotNull('reply_path')
            ->orderBy('id')
            ->get(['id', 'reply_path', 'reply_notes', 'completed_at', 'created_at', 'updated_at'])
            ->each(function (object $request): void {
                $createdAt = $request->completed_at ?? $request->updated_at ?? $request->created_at;

                DB::table('price_request_replies')->insert([
                    'price_request_id' => $request->id,
                    // The legacy schema did not record who uploaded the file.
                    'uploaded_by' => null,
                    'version' => 1,
                    'file_path' => $request->reply_path,
                    'notes' => $request->reply_notes,
                    'is_legacy' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_request_replies');
    }
};
