<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('password');
            $table->string('phone', 20)->nullable()->after('role');
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_seller')->default(false);
            $table->boolean('seller_verified')->default(false);
            $table->unsignedTinyInteger('rating_avg')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'phone', 'city', 'district', 'bio', 'avatar_path',
                'is_seller', 'seller_verified', 'rating_avg', 'rating_count',
            ]);
        });
    }
};
