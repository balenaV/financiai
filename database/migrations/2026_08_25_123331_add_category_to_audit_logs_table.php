<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "acesso" (login), "alteracao" (mutação normal) ou "alerta" (bloqueio/
     * tentativa suspeita) — é o selo do bloco "Atividade da conta".
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('category', 16)->default('alteracao')->after('event');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
