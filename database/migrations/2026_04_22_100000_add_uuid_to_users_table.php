<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agrega columna nullable
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // 2. Backfill: asigna UUID a todos los usuarios existentes
        DB::table('users')->whereNull('uuid')->orderBy('id')->each(function ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        });

        // 3. Convierte a NOT NULL + UNIQUE
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
