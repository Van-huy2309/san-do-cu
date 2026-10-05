<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('min_order')->default(0);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('code');
            $table->index(['seller_id', 'is_active']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        Schema::create('marketing_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('slot_limit');
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['marketing_package_id', 'ends_at']);
            $table->index(['listing_id', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('total_price')->constrained('vouchers')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0)->after('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn('discount_amount');
        });
        Schema::dropIfExists('marketing_enrollments');
        Schema::dropIfExists('marketing_packages');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
