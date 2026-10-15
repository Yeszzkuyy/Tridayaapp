<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk kolom filter/sort rutin (dashboard, monitoring,
     * pipeline, pencarian). Class-index bawaan FK tidak diubah.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('status');
            $table->index('incoming_date');
            $table->index('closed_at');
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'incoming_date']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('pt_group');
            $table->index('name');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->index('meeting_date');
            $table->index(['customer_id', 'meeting_date']);
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->index('follow_up_date');
            $table->index(['customer_id', 'follow_up_date']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'issue_date']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['status', 'issue_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['invoice_id', 'payment_date']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('start_date');
            $table->index('project_name');
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['incoming_date']);
            $table->dropIndex(['closed_at']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['status', 'incoming_date']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['pt_group']);
            $table->dropIndex(['name']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex(['meeting_date']);
            $table->dropIndex(['customer_id', 'meeting_date']);
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex(['follow_up_date']);
            $table->dropIndex(['customer_id', 'follow_up_date']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'issue_date']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'issue_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id', 'payment_date']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['start_date']);
            $table->dropIndex(['project_name']);
        });

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex(['lead_id', 'created_at']);
        });
    }
};
