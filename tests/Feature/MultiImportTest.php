<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MultiImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real: duas linhas idênticas dentro do MESMO arquivo não eram
     * marcadas como duplicadas entre si (a checagem só olhava para
     * transações já commitadas) — a segunda estourava a constraint única do
     * banco só na hora do commit e desfazia o lote inteiro (0 transações
     * importadas, nem a linha legítima).
     */
    public function test_duplicate_lines_within_the_same_file_do_not_corrupt_the_whole_commit(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $csv = "Data;Descrição;Valor\n25/07/2026;Mercado;-125,90\n25/07/2026;Mercado;-125,90\n26/07/2026;Padaria;-12,00\n";

        $batchId = $this->createAndPreviewCsv($user, $account, $csv);
        $rows = $this->getJson(route('transactions.import.show', $batchId))->json('rows');
        $this->assertCount(3, $rows);

        $statuses = collect($rows)->countBy('status');
        $this->assertSame(1, $statuses->get('duplicate_probable', 0), 'a segunda linha repetida deveria ser marcada como duplicada');

        $rowIds = collect($rows)->where('included', true)->pluck('id')->all();
        $commit = $this->postJson(route('transactions.import.commit', $batchId), ['row_ids' => $rowIds]);

        $commit->assertSuccessful();
        $this->assertSame(2, $commit->json('imported'));
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['description' => 'Mercado', 'user_id' => $user->id]);
        $this->assertDatabaseHas('transactions', ['description' => 'Padaria', 'user_id' => $user->id]);
    }

    /**
     * Fluxo real de uso: dois arquivos importados em sequência (meses
     * consecutivos, com uma transação na borda repetida entre os dois —
     * cenário comum quando o usuário baixa extratos com alguns dias de
     * sobreposição). O resultado de cada arquivo é isolado: o segundo não
     * duplica o que o primeiro já trouxe, e nenhum dos dois se mistura com
     * o outro.
     */
    public function test_two_files_imported_in_sequence_deduplicate_across_files_and_stay_isolated(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $julyCsv = "Data;Descrição;Valor\n15/07/2026;Salário;3000,00\n28/07/2026;Mercado;-80,00\n31/07/2026;Assinatura Streaming;-39,90\n";
        $firstBatchId = $this->importCsv($user, $account, $julyCsv);
        $this->assertDatabaseCount('transactions', 3);

        // Agosto reexporta a transação de borda de 31/07 (comum quando o
        // extrato de agosto inclui o fim de julho) e traz duas novas.
        $augustCsv = "Data;Descrição;Valor\n31/07/2026;Assinatura Streaming;-39,90\n05/08/2026;Farmácia;-45,00\n15/08/2026;Salário;3000,00\n";
        $secondBatchId = $this->createAndPreviewCsv($user, $account, $augustCsv);
        $secondRows = $this->getJson(route('transactions.import.show', $secondBatchId))->json('rows');
        $this->assertCount(3, $secondRows);

        $streamingRow = collect($secondRows)->firstWhere('description', 'Assinatura Streaming');
        $this->assertSame('duplicate_probable', $streamingRow['status']);
        $this->assertFalse($streamingRow['included']);

        $rowIds = collect($secondRows)->where('included', true)->pluck('id')->all();
        $secondCommit = $this->postJson(route('transactions.import.commit', $secondBatchId), ['row_ids' => $rowIds]);
        $secondCommit->assertSuccessful();
        $this->assertSame(2, $secondCommit->json('imported'));

        // Total: 3 (julho) + 2 novas de agosto = 5. A duplicata de borda não entrou de novo.
        $this->assertDatabaseCount('transactions', 5);
        $this->assertSame(1, Transaction::where('description', 'Assinatura Streaming')->count());

        // Os dois lotes continuam rastreáveis e isolados um do outro.
        $this->assertDatabaseHas('import_batches', ['id' => $firstBatchId, 'status' => 'committed', 'rows_imported' => 3]);
        $this->assertDatabaseHas('import_batches', ['id' => $secondBatchId, 'status' => 'committed', 'rows_imported' => 2]);
    }

    /**
     * Um arquivo com uma linha inválida (data ilegível) não pode impedir as
     * outras linhas do mesmo arquivo de serem importadas, nem afetar um
     * arquivo importado antes dele.
     */
    public function test_an_invalid_row_does_not_block_the_rest_of_the_file_or_a_previous_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $goodCsv = "Data;Descrição;Valor\n01/07/2026;Salário;3000,00\n";
        $firstBatchId = $this->importCsv($user, $account, $goodCsv);
        $this->assertDatabaseCount('transactions', 1);

        $mixedCsv = "Data;Descrição;Valor\ndata-invalida;Mercado;-50,00\n10/08/2026;Farmácia;-30,00\n";
        $secondBatchId = $this->createAndPreviewCsv($user, $account, $mixedCsv);
        $rows = $this->getJson(route('transactions.import.show', $secondBatchId))->json('rows');
        $this->assertCount(2, $rows);
        $invalidRow = collect($rows)->firstWhere('description', '');
        $this->assertNotNull($invalidRow);
        $this->assertSame('invalid', $invalidRow['status']);

        $rowIds = collect($rows)->where('included', true)->pluck('id')->all();
        $this->postJson(route('transactions.import.commit', $secondBatchId), ['row_ids' => $rowIds])
            ->assertSuccessful()
            ->assertJson(['imported' => 1]);

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['description' => 'Salário', 'user_id' => $user->id]);
        $this->assertDatabaseHas('transactions', ['description' => 'Farmácia', 'user_id' => $user->id]);
        $this->assertDatabaseHas('import_batches', ['id' => $firstBatchId, 'status' => 'committed', 'rows_imported' => 1]);
    }

    /**
     * Achado de segurança: a rede de segurança do commit só olhava para
     * "fingerprint" repetido, não para "external_id" repetido — que é a
     * OUTRA constraint única da tabela (user_id, account_id, external_id).
     * Uma linha marcada duplicate_exact por external_id que o usuário força
     * a incluir mesmo assim (fingerprint diferente, então passava pelo
     * primeiro filtro) ainda conseguia estourar a constraint e derrubar o
     * lote inteiro.
     */
    public function test_forcing_a_duplicate_external_id_does_not_crash_the_commit(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $firstCsv = "Data;Descrição;Valor;Id\n01/07/2026;Compra original;-50,00;ABC123\n";
        $this->importCsv($user, $account, $firstCsv);
        $this->assertDatabaseCount('transactions', 1);

        // Mesmo Id (external_id), mas descrição/valor diferentes — fingerprint
        // diferente, então NÃO cai no filtro de fingerprint repetido; só o
        // filtro de external_id repetido (achado #12) evita o crash aqui.
        $secondCsv = "Data;Descrição;Valor;Id\n01/07/2026;Compra original;-50,00;ABC123\n02/07/2026;Compra nova;-30,00;XYZ789\n";
        $secondBatchId = $this->createAndPreviewCsv($user, $account, $secondCsv);
        $rows = $this->getJson(route('transactions.import.show', $secondBatchId))->json('rows');
        $duplicateRow = collect($rows)->first(fn ($r) => $r['status'] === 'duplicate_exact');
        $this->assertNotNull($duplicateRow, 'a linha com Id repetido deveria vir marcada duplicate_exact');

        // Usuário força a inclusão da linha duplicada mesmo assim (ex.: marcou
        // manualmente na revisão) junto com a legítima.
        $allRowIds = collect($rows)->pluck('id')->all();
        $commit = $this->postJson(route('transactions.import.commit', $secondBatchId), ['row_ids' => $allRowIds]);

        $commit->assertSuccessful();
        $this->assertSame(1, $commit->json('imported'));
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame(1, Transaction::where('external_id', 'ABC123')->count());
    }

    private function importCsv(User $user, Account $account, string $csv): int
    {
        $batchId = $this->createAndPreviewCsv($user, $account, $csv);
        $rows = $this->getJson(route('transactions.import.show', $batchId))->json('rows');
        $rowIds = collect($rows)->where('included', true)->pluck('id')->all();

        $this->postJson(route('transactions.import.commit', $batchId), ['row_ids' => $rowIds])->assertSuccessful();

        return $batchId;
    }

    private function createAndPreviewCsv(User $user, Account $account, string $csv): int
    {
        $store = $this->actingAs($user)->postJson(route('transactions.import.store'), [
            'account_id' => $account->id,
            'file' => UploadedFile::fake()->createWithContent('extrato.csv', $csv),
        ])->assertSuccessful();

        $batchId = $store->json('batch_id');
        $mapping = $store->json('suggested_mapping');

        $this->postJson(route('transactions.import.preview', $batchId), [
            'column_map' => $mapping,
            'date_format' => 'DD/MM/AAAA',
            'decimal_separator' => 'virgula',
        ])->assertSuccessful();

        return $batchId;
    }
}
