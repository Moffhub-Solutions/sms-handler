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
            $table->decimal('estimated_cost', 10, 4)->nullable()->after('response');
            $table->unsignedSmallInteger('segment_count')->nullable()->after('estimated_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['estimated_cost', 'segment_count']);
        });
    }
};
