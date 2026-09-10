<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contas criadas por login social recebem uma senha aleatória de 64
            // chars que o dono nunca conhece — sem esta flag não há como saber
            // se "current_password" é uma exigência que o usuário consegue
            // cumprir ou uma porta trancada. Default true: linhas existentes
            // seguem exigindo senha (nenhuma regressão de segurança), e quem é
            // OAuth-only entre elas se resolve pelo re-consentimento, que fica
            // disponível para qualquer conta com provedor vinculado.
            $table->boolean('has_usable_password')->default(true)->after('password');

            // Secret da ativação em andamento. Fica separado de
            // two_factor_secret para que abandonar o modal no meio não derrube
            // um MFA já confirmado (achado M1).
            $table->text('two_factor_pending_secret')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_usable_password', 'two_factor_pending_secret']);
        });
    }
};
