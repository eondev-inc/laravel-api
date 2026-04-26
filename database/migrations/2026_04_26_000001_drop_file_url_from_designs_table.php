<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->string('file_url')->after('file_path');
        });
    }
};
