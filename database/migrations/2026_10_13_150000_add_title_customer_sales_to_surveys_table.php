<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Survey berdiri sendiri: punya judul + customer + sales langsung,
     * project jadi opsional (survey biasanya dibuat SEBELUM project ada).
     */
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('title')->nullable()->after('id');
            $table->foreignId('customer_id')->nullable()->after('project_id')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('sales_user_id')->nullable()->after('customer_id')
                ->constrained('users')->nullOnDelete();
            $table->index('sales_user_id');
        });

        // Data lama: judul + customer + sales diwarisi dari project-nya
        // supaya tidak ada data yang hilang atau kehilangan akses.
        DB::table('surveys')->orderBy('id')->chunkById(100, function ($surveys) {
            foreach ($surveys as $survey) {
                $project = DB::table('projects')->find($survey->project_id);

                DB::table('surveys')->where('id', $survey->id)->update([
                    'title' => $survey->title
                        ?? ($project->project_name ?? 'Survey #'.$survey->id),
                    'customer_id' => $survey->customer_id
                        ?? ($project->customer_id ?? null),
                    'sales_user_id' => $survey->sales_user_id
                        ?? ($project->sales_user_id ?? null),
                ]);
            }
        });

        // Project jadi opsional: FK dilepas dulu agar kolom boleh NULL,
        // lalu dipasang lagi dengan nullOnDelete (survey tidak ikut terhapus
        // saat project dihapus — survey berdiri sendiri sekarang).
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['sales_user_id']);
            $table->dropConstrainedForeignId('sales_user_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('title');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
