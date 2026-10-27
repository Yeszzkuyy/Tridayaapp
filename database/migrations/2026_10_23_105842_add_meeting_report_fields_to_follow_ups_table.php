<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            // Meeting Report / MOM fields — populated when sales completes a meeting
            $table->json('meeting_photos')->nullable()->after('completed_at')
                  ->comment('Array of photo paths (max 3) documenting the meeting');
            $table->text('meeting_notes')->nullable()->after('meeting_photos')
                  ->comment('Minutes of Meeting: discussion points, agreements, action items');
            $table->string('meeting_outcome', 32)->nullable()->after('meeting_notes')
                  ->comment('prospect|need_quotation|follow_up|negotiation|closed_won|closed_lost');
            $table->date('meeting_next_action_date')->nullable()->after('meeting_outcome')
                  ->comment('Next agreed action date from the meeting');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_photos',
                'meeting_notes',
                'meeting_outcome',
                'meeting_next_action_date',
            ]);
        });
    }
};
