<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('traffic_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45);
            $table->string('scope', 20);
            $table->unsignedInteger('hits');
            $table->string('sample_path', 180)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_alerts');
        Schema::dropIfExists('ip_bans');
    }
};
