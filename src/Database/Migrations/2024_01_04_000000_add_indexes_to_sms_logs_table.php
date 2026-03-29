<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index('provider');
            $table->index('to');
            $table->index('created_at');
            $table->index(['provider', 'delivery_status'], 'sms_logs_provider_delivery_status_index');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['to']);
            $table->dropIndex(['created_at']);
            $table->dropIndex('sms_logs_provider_delivery_status_index');
            $table->dropIndex(['scheduled_at']);
        });
    }
};
