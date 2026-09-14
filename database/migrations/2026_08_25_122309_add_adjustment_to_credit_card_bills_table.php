<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajuste manual da fatura em aberto (acréscimo/desconto + motivo).
     * Guardado à parte de total_amount para o valor não se perder na
     * reabertura do modal de edição e para não confundir com compras reais.
     */
    public function up(): void
    {
        Schema::table('credit_card_bills', function (Blueprint $table) {
            $table->decimal('adjustment_amount', 19, 2)->default('0.00')->after('total_amount');
            $table->string('adjustment_reason')->nullable()->after('adjustment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_bills', function (Blueprint $table) {
            $table->dropColumn(['adjustment_amount', 'adjustment_reason']);
        });
    }
};
