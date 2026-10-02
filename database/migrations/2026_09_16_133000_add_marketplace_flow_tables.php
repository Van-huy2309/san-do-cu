<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_status', 20)->default('none')->after('seller_verified');
            $table->string('kyc_id_last4', 8)->nullable()->after('kyc_status');
            $table->string('kyc_full_name')->nullable()->after('kyc_id_last4');
            $table->string('kyc_front_path')->nullable()->after('kyc_full_name');
            $table->string('kyc_back_path')->nullable()->after('kyc_front_path');
            $table->text('kyc_note')->nullable()->after('kyc_back_path');
            $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_note');
            $table->unsignedBigInteger('wallet_balance')->default(0)->after('kyc_reviewed_at');
            $table->unsignedBigInteger('wallet_frozen')->default(0)->after('wallet_balance');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->json('extras')->nullable()->after('city');
            $table->timestamp('featured_until')->nullable()->after('is_featured');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedInteger('offer_amount')->nullable()->after('body');
            $table->string('offer_status', 20)->nullable()->after('offer_amount');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedInteger('accepted_price')->nullable()->after('seller_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('escrow_status', 20)->default('none')->after('status');
            $table->unsignedBigInteger('escrow_amount')->default(0)->after('escrow_status');
            $table->timestamp('received_at')->nullable()->after('escrow_amount');
            $table->timestamp('released_at')->nullable()->after('received_at');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('search_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('min_price')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->text('detail')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('resolution', 20)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('search_alerts');
        Schema::dropIfExists('wallet_transactions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['escrow_status', 'escrow_amount', 'received_at', 'released_at']);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('accepted_price');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['offer_amount', 'offer_status']);
        });
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['extras', 'featured_until']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_status', 'kyc_id_last4', 'kyc_full_name', 'kyc_front_path', 'kyc_back_path',
                'kyc_note', 'kyc_reviewed_at', 'wallet_balance', 'wallet_frozen',
            ]);
        });
    }
};
