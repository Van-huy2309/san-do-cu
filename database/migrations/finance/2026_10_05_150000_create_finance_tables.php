<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance')->create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('bank_name', 80);
            $table->text('account_holder');
            $table->text('account_number');
            $table->string('account_last4', 4);
            $table->timestamps();
        });

        Schema::connection('finance')->create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->default(0);
            $table->string('type', 30);
            $table->unsignedBigInteger('amount');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['order_id', 'seller_id', 'type']);
            $table->index(['type', 'occurred_at']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::connection('finance')->dropIfExists('ledger_entries');
        Schema::connection('finance')->dropIfExists('bank_accounts');
    }
};
