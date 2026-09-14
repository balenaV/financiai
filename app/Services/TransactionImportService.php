<?php

namespace App\Services;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportFormat;
use App\Enums\ImportRowStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\ParseImportBatchJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use App\Support\MerchantCategoryDictionary;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionImportService
{
    /** @return array{batch:ImportBatch,columns:?list<string>,suggested_mapping:?array} */
    public function createBatch(User $user, Account $account, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $format = match ($extension) {
            'csv', 'txt' => ImportFormat::Csv,
            'ofx' => ImportFormat::Ofx,
            default => throw new RuntimeException('Formato de arquivo não suportado.'),
        };

        $storedPath = $file->storeAs('imports', Str::random(40).'.'.$extension, 'local');

        $batch = $user->importBatches()->create([
            'account_id' => $account->id,
            'filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'format' => $format,
            'status' => ImportBatchStatus::Pending,
            'stored_path' => $storedPath,
        ]);

        $columns = null;
        $suggestedMapping = null;

        if ($format === ImportFormat::Csv) {
            $columns = $this->sniffCsvColumns($storedPath);
            $suggestedMapping = $this->suggestedMapping($user, $columns);
        }

        return [
            'batch' => $batch,
            'columns' => $columns,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /** @param array<string,string>|null $columnMap */
    public function dispatchParse(ImportBatch $batch, ?array $columnMap = null, ?string $dateFormat = null, ?string $decimalSeparator = null): void
    {
        $storedPath = $batch->stored_path;
        if ($storedPath === null) {
            throw new RuntimeException('Arquivo desta importação não está mais disponível.');
        }

        DB::transaction(function () use ($batch) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ImportBatchStatus::Pending) {
                throw new RuntimeException('Esta importação já está sendo processada.');
            }

            $batch->update(['status' => ImportBatchStatus::Parsing]);
        });

        ParseImportBatchJob::dispatch($batch, $storedPath, $columnMap, $dateFormat, $decimalSeparator);
    }

    /** @param array<string,string>|null $columnMap */
    public function parseIntoStaging(ImportBatch $batch, string $storedPath, ?array $columnMap, ?string $dateFormat, ?string $decimalSeparator): void
    {
        $contents = Storage::disk('local')->get($storedPath);
        if ($contents === null) {
            throw new RuntimeException('Não foi possível ler o arquivo.');
        }

        $rows = $batch->format === ImportFormat::Ofx
            ? $this->fromOfx($contents)
            : $this->fromCsv($contents, $columnMap, $dateFormat, $decimalSeparator);

        $user = $batch->user;
        $dates = [];
        $read = 0;
        // Fingerprints/external_ids já vistos nesta mesma leitura — sem isso,
        // duas linhas idênticas dentro de UM único arquivo (ex.: banco
        // exportou a mesma compra duas vezes) chegavam ambas como "new" e a
        // segunda estourava a constraint única só na hora de confirmar,
        // derrubando o commit inteiro do lote (ver commit() abaixo).
        $seenFingerprints = [];
        $seenExternalIds = [];

        DB::transaction(function () use ($rows, $batch, $user, &$dates, &$read, &$seenFingerprints, &$seenExternalIds) {
            foreach ($rows as $row) {
                $read++;

                try {
                    $amount = Money::normalize((string) ($row['amount'] ?? '0'));
                    $date = Carbon::parse($row['date'] ?? null)->toDateString();
                    $description = trim((string) ($row['description'] ?? ''));
                } catch (\Throwable) {
                    $batch->rows()->create([
                        'posted_at' => today(),
                        'description_raw' => (string) ($row['description'] ?? ''),
                        'description' => '',
                        'type' => TransactionType::Expense,
                        'amount' => '0',
                        'fingerprint' => Str::random(40),
                        'status' => ImportRowStatus::Invalid,
                        'included' => false,
                        'line_number' => $read,
                    ]);

                    continue;
                }

                if ($description === '' || bccomp($amount, '0', 2) === 0) {
                    $batch->rows()->create([
                        'posted_at' => $date,
                        'description_raw' => (string) ($row['description'] ?? ''),
                        'description' => $description,
                        'type' => TransactionType::Expense,
                        'amount' => '0',
                        'fingerprint' => Str::random(40),
                        'status' => ImportRowStatus::Invalid,
                        'included' => false,
                        'line_number' => $read,
                    ]);

                    continue;
                }

                $dates[] = $date;
                $type = str_starts_with($amount, '-') ? TransactionType::Expense : TransactionType::Income;
                $absolute = ltrim($amount, '-');
                $externalId = trim((string) ($row['external_id'] ?? '')) ?: null;
                $normalizedDescription = $this->normalizeDescription($description);
                $fingerprint = $this->fingerprint($batch->account_id, $date, $normalizedDescription, $absolute);
                $status = ($externalId !== null && isset($seenExternalIds[$externalId]))
                    ? ImportRowStatus::DuplicateExact
                    : (isset($seenFingerprints[$fingerprint])
                        ? ImportRowStatus::DuplicateProbable
                        : $this->duplicateStatus($user->id, $batch->account_id, $externalId, $fingerprint));
                $suggestedCategoryId = in_array($status, [ImportRowStatus::DuplicateExact, ImportRowStatus::DuplicateProbable], true)
                    ? null
                    : $this->suggestCategory($user, $type, $normalizedDescription);

                if ($status === ImportRowStatus::New && $suggestedCategoryId === null) {
                    $status = ImportRowStatus::NeedsCategory;
                }

                $seenFingerprints[$fingerprint] = true;
                if ($externalId !== null) {
                    $seenExternalIds[$externalId] = true;
                }

                $batch->rows()->create([
                    'posted_at' => $date,
                    'description_raw' => (string) ($row['description'] ?? $description),
                    'description' => Str::limit($description, 180, ''),
                    'type' => $type,
                    'amount' => $absolute,
                    'external_id' => $externalId,
                    'fingerprint' => $fingerprint,
                    'suggested_category_id' => $suggestedCategoryId,
                    'status' => $status,
                    'included' => ! in_array($status, [ImportRowStatus::DuplicateExact, ImportRowStatus::DuplicateProbable], true),
                ]);
            }

            $batch->update([
                'status' => ImportBatchStatus::Parsed,
                'rows_read' => $read,
                'period_start' => $dates === [] ? null : min($dates),
                'period_end' => $dates === [] ? null : max($dates),
                'stored_path' => null,
            ]);
        });
    }

    /** @param list<int> $includedRowIds
     *  @param array<int,int> $categoryOverrides row id => category id
     *  @return array{imported:int,skipped:int,auto_categorized:int} */
    public function commit(ImportBatch $batch, array $includedRowIds, array $categoryOverrides = []): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'auto_categorized' => 0];

        DB::transaction(function () use ($batch, $includedRowIds, $categoryOverrides, &$result) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ImportBatchStatus::Parsed) {
                throw new RuntimeException('Este lote não está pronto para ser confirmado.');
            }

            $rows = $batch->rows()->whereIn('id', $includedRowIds)->where('status', '!=', ImportRowStatus::Invalid)->get();

            // Segunda rede de segurança além da marcação em parseIntoStaging():
            // se mesmo assim duas linhas incluídas baterem em qualquer uma das
            // DUAS constraints únicas da tabela — (user_id, import_hash) ou
            // (user_id, account_id, external_id) — pular a repetida em vez de
            // deixar o banco estourar e desfazer o commit inteiro do lote.
            $committedFingerprints = [];
            $committedExternalIds = [];

            foreach ($rows as $row) {
                $externalIdTaken = $row->external_id !== null && (
                    isset($committedExternalIds[$row->external_id])
                    || Transaction::where('user_id', $batch->user_id)
                        ->where('account_id', $batch->account_id)
                        ->where('external_id', $row->external_id)
                        ->exists()
                );

                if ($externalIdTaken
                    || isset($committedFingerprints[$row->fingerprint])
                    || Transaction::where('user_id', $batch->user_id)->where('import_hash', $row->fingerprint)->exists()) {
                    continue;
                }
                $committedFingerprints[$row->fingerprint] = true;
                if ($row->external_id !== null) {
                    $committedExternalIds[$row->external_id] = true;
                }

                $categoryId = array_key_exists($row->id, $categoryOverrides) ? $categoryOverrides[$row->id] : $row->suggested_category_id;
                if ($categoryId !== null && ! Category::where('id', $categoryId)->where('user_id', $batch->user_id)->exists()) {
                    $categoryId = null;
                }

                if ($categoryId !== null && $categoryId !== $row->suggested_category_id) {
                    $this->reinforceCategoryRule($batch->user, $row->description_raw, $categoryId);
                }

                $batch->user->transactions()->create([
                    'account_id' => $batch->account_id,
                    'category_id' => $categoryId,
                    'type' => $row->type,
                    'payment_channel' => 'account',
                    'description' => $row->description,
                    'amount' => $row->amount,
                    'competence_date' => $row->posted_at,
                    'due_date' => $row->posted_at,
                    'paid_at' => $row->posted_at,
                    'status' => TransactionStatus::Completed,
                    'notes' => 'Importada de '.$batch->account->name,
                    'source_type' => 'file_import',
                    'external_id' => $row->external_id,
                    'import_hash' => $row->fingerprint,
                    'import_batch_id' => $batch->id,
                ]);

                $result['imported']++;
                if ($row->suggested_category_id !== null && ! array_key_exists($row->id, $categoryOverrides)) {
                    $result['auto_categorized']++;
                }
            }

            $result['skipped'] = $batch->rows()->count() - $result['imported'];

            $batch->update([
                'status' => ImportBatchStatus::Committed,
                'rows_imported' => $result['imported'],
                'rows_duplicated' => $batch->rows()->whereIn('status', [ImportRowStatus::DuplicateExact, ImportRowStatus::DuplicateProbable])->count(),
            ]);
        });

        return $result;
    }

    public function revert(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ImportBatchStatus::Committed) {
                throw new RuntimeException('Este lote não pode ser desfeito.');
            }

            $batch->transactions()->delete();
            $batch->update(['status' => ImportBatchStatus::Reverted]);
        });
    }

    /** @return list<string> */
    private function sniffCsvColumns(string $storedPath): array
    {
        $contents = Storage::disk('local')->get($storedPath) ?? '';
        $contents = $this->stripBom($contents);
        $firstLine = strtok($contents, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        return array_map(fn ($header) => trim((string) $header), str_getcsv($firstLine, $delimiter) ?: []);
    }

    /** @param list<string> $columns
     *  @return array<string,string>|null */
    private function suggestedMapping(User $user, array $columns): ?array
    {
        $hint = hash('sha256', implode('|', array_map(fn ($c) => Str::lower($c), $columns)));
        $mapping = $user->importMappings()->where('institution_hint', $hint)->first();

        if ($mapping) {
            return $mapping->column_map;
        }

        $guessed = [];
        foreach ($columns as $column) {
            $normalized = $this->normalizeHeader($column);
            if (in_array($normalized, ['date', 'description', 'amount', 'external_id'], true)) {
                $guessed[$normalized] = $column;
            }
        }

        return $guessed === [] ? null : $guessed;
    }

    /** @return list<array{date:string,description:string,amount:string,external_id?:string}> */
    private function fromCsv(string $contents, ?array $columnMap, ?string $dateFormat, ?string $decimalSeparator): array
    {
        $contents = $this->stripBom($contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $firstLine = $lines[0] ?? '';
        $delimiter = $this->detectDelimiter($firstLine);

        $headers = array_map(fn ($h) => trim((string) $h), str_getcsv($firstLine, $delimiter) ?: []);
        $map = $columnMap ?: array_combine(
            array_map(fn ($h) => $this->normalizeHeader($h), $headers),
            $headers,
        );

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter) ?: [];
            $record = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
            if (! is_array($record)) {
                continue;
            }

            $date = $map['date'] ?? null ? ($record[$map['date']] ?? '') : '';
            $date = $this->normalizeDate((string) $date, $dateFormat);
            $amountRaw = $map['amount'] ?? null ? ($record[$map['amount']] ?? '') : '';
            $amount = $decimalSeparator === 'ponto' ? str_replace(',', '', (string) $amountRaw) : (string) $amountRaw;

            $rows[] = [
                'date' => $date,
                'description' => (string) ($map['description'] ?? null ? ($record[$map['description']] ?? '') : ''),
                'amount' => $amount,
                'external_id' => (string) ($map['external_id'] ?? null ? ($record[$map['external_id']] ?? '') : ''),
            ];
        }

        return $rows;
    }

    /** @return list<array{date:string,description:string,amount:string,external_id:string}> */
    private function fromOfx(string $contents): array
    {
        $contents = $this->normalizeOfxEncoding($contents);

        $matchCount = preg_match_all('/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>|<\/BANKTRANLIST>))/is', $contents, $matches);
        if (! $matchCount) {
            return [];
        }

        return array_map(function (string $block) {
            $field = fn (string $name): string => preg_match('/<'.$name.'>([^<\r\n]+)/i', $block, $match)
                ? trim($match[1])
                : '';
            $rawDate = preg_replace('/\D/', '', $field('DTPOSTED'));
            $amount = $field('TRNAMT');

            // O tipo (receita/despesa) na etapa seguinte é decidido só pelo sinal
            // do valor. A maioria dos bancos assina TRNAMT corretamente, mas
            // algumas exportações mandam débitos com valor positivo — sem essa
            // correção a transação vira "receita" e nunca bate com o dicionário
            // de despesas na categorização automática. Lista conservadora: só
            // tipos inequivocamente de débito por spec OFX (evita forçar sinal
            // em tipos ambíguos como XFER/CASH/OTHER, que podem ser crédito).
            $debitTypes = ['DEBIT', 'POS', 'ATM', 'FEE', 'SRVCHG', 'PAYMENT', 'DIRECTDEBIT', 'CHECK'];
            if ($amount !== '' && ! str_starts_with($amount, '-') && in_array(strtoupper($field('TRNTYPE')), $debitTypes, true)) {
                $amount = '-'.$amount;
            }

            return [
                'date' => strlen((string) $rawDate) >= 8 ? substr((string) $rawDate, 0, 8) : '',
                'description' => $field('MEMO') ?: $field('NAME'),
                'amount' => $amount,
                'external_id' => $field('FITID'),
            ];
        }, $matches[1]);
    }

    private function normalizeOfxEncoding(string $contents): string
    {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        $encoding = 'Windows-1252';
        if (preg_match('/CHARSET:\s*([\w-]+)/i', $contents, $match)) {
            $charset = strtoupper($match[1]);
            $encoding = match (true) {
                str_contains($charset, '8859') => 'ISO-8859-1',
                default => 'Windows-1252',
            };
        }

        return mb_convert_encoding($contents, 'UTF-8', $encoding);
    }

    private function stripBom(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
    }

    private function normalizeDate(string $date, ?string $dateFormat): string
    {
        if ($date === '') {
            return $date;
        }

        return match ($dateFormat) {
            'MM/DD/AAAA' => preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) ? "{$m[3]}-{$m[1]}-{$m[2]}" : $date,
            'AAAA-MM-DD' => $date,
            default => preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : $date,
        };
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::of($header)->trim()->lower()->ascii()->replace([' ', '-'], '_')->toString();

        return match ($normalized) {
            'data', 'data_lancamento', 'data_da_transacao' => 'date',
            'descricao', 'historico', 'memo', 'nome' => 'description',
            'valor', 'quantia' => 'amount',
            'id', 'fitid', 'identificador', 'documento' => 'external_id',
            default => $normalized,
        };
    }

    private function normalizeDescription(string $description): string
    {
        return Str::of($description)
            ->lower()
            ->ascii()
            ->replaceMatches('/\d{6,}/', '')
            ->replaceMatches('/[^\w\s]/', '')
            ->squish()
            ->toString();
    }

    private function fingerprint(int $accountId, string $date, string $normalizedDescription, string $absoluteAmount): string
    {
        return hash('sha256', implode('|', [
            $accountId,
            $date,
            $normalizedDescription,
            Money::toMinor($absoluteAmount),
        ]));
    }

    /** Regra do usuário → dicionário de merchants → MCC (indisponível em OFX/CSV hoje) → sem categoria. */
    private function suggestCategory(User $user, TransactionType $type, string $normalizedDescription): ?int
    {
        $pattern = $this->merchantKey($normalizedDescription);

        if ($pattern !== '') {
            $rule = $user->categoryRules()->where('pattern', $pattern)->first();
            if ($rule) {
                return $rule->category_id;
            }
        }

        $match = MerchantCategoryDictionary::match($normalizedDescription, $type);
        if ($match) {
            $category = $user->categories()
                ->where('name', $match['category'])
                ->where('type', $match['type'])
                ->where('active', true)
                ->first();

            if ($category) {
                return $category->id;
            }
        }

        return null;
    }

    private function merchantKey(string $normalizedDescription): string
    {
        return Str::of($normalizedDescription)->explode(' ')->first(fn ($word) => $word !== '') ?? '';
    }

    /** Toda vez que o usuário corrige (ou define) a categoria de uma linha,
     *  a regra daquele padrão de merchant é criada ou atualizada — é esse
     *  aprendizado que sobrevive à troca de fonte de dados. */
    private function reinforceCategoryRule(User $user, string $descriptionRaw, int $categoryId): void
    {
        $pattern = $this->merchantKey($this->normalizeDescription($descriptionRaw));
        if ($pattern === '') {
            return;
        }

        $user->categoryRules()->updateOrCreate(
            ['pattern' => $pattern],
            ['category_id' => $categoryId, 'source' => 'user'],
        )->increment('hit_count');
    }

    private function duplicateStatus(int $userId, int $accountId, ?string $externalId, string $fingerprint): ImportRowStatus
    {
        if ($externalId !== null) {
            $exists = Transaction::where('user_id', $userId)
                ->where('account_id', $accountId)
                ->where('external_id', $externalId)
                ->exists();

            if ($exists) {
                return ImportRowStatus::DuplicateExact;
            }
        }

        $exists = Transaction::where('user_id', $userId)
            ->where('import_hash', $fingerprint)
            ->exists();

        return $exists ? ImportRowStatus::DuplicateProbable : ImportRowStatus::New;
    }
}
