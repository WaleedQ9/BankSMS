<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE budget_alerts MODIFY alert_type ENUM('warning_80', 'exceeded_100', 'monthly_60', 'monthly_80', 'monthly_100', 'weekly_60', 'weekly_100') NOT NULL");
    }

    public function down(): void
    {
        DB::table('budget_alerts')->whereIn('alert_type', ['monthly_60', 'monthly_80', 'monthly_100', 'weekly_60', 'weekly_100'])->delete();
        DB::statement("ALTER TABLE budget_alerts MODIFY alert_type ENUM('warning_80', 'exceeded_100') NOT NULL");
    }
};
