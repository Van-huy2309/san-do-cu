<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verify_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('email_verify_expires_at')->nullable()->after('email_verify_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verify_code_hash', 'email_verify_expires_at']);
        });
    }
};
