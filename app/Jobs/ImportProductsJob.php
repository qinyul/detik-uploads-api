<?php

namespace App\Jobs;

use App\Facades\Audit;
use App\Models\ImportJob;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $importJobId
    ) {}

    private function readCsvInChunks(string $path, int $batchSize): \Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV file: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new \RuntimeException("CSV header not found");
            }
            $chunk = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($header)) {
                    Audit::warning('CSV column mismatch', [
                        'job_id'        => $this->importJobId,
                        'row_number'    => $rowNumber,
                        'expected_cols' => count($header),
                        'actual_cols'   => count($row),
                    ]);
                    continue;
                }

                $chunk[] = [
                    'row_number' => $rowNumber,
                    'data' => array_combine($header, $row),
                ];

                if (count($chunk) === $batchSize) {
                    Audit::debug('CSV chunk ready', [
                        'job_id'     => $this->importJobId,
                        'chunk_size' => count($chunk),
                        'last_row'   => $rowNumber,
                    ]);
                    yield $chunk;
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    private function validateRow(array $data): array
    {
        return validator($data, [
            'name'  => 'required|string|max:255',
            'sku'   => 'required|string|max:100',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0',
        ])->validate();
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $batchSize = config('import.products_batch_size', 500);
        $importJob = ImportJob::findOrFail($this->importJobId);
        $importJob->update(['status' => ImportJob::STATUS_IN_PROGRESS]);

        Audit::info('Import configuration', [
            'job_id'     => $importJob->id,
            'file'       => $importJob->filename,
            'batch_size' => $batchSize,
        ]);

        $path = Storage::path($importJob->filename);

        $total = 0;
        $success = 0;
        $failed = 0;

        DB::transaction(function () use (
            $path,
            $importJob,
            $batchSize,
            &$total,
            &$success,
            &$failed,
        ) {

            foreach ($this->readCsvInChunks($path, $batchSize) as $chunk) {
                foreach ($chunk as  $row) {
                    $total++;
                    $csvRowNumber = $row['row_number'];
                    $data = $row['data'];

                    try {
                        $validated = $this->validateRow($data);

                        Product::create([
                            'name' => trim($validated['name']),
                            'sku' => trim($validated['sku']),
                            'price' => $validated['price'],
                            'stock' => (int) $validated['stock']
                        ]);
                        $success++;
                    } catch (\Throwable $e) {
                        $failed++;

                        Audit::error('Product import failed', [
                            'job_id'     => $importJob->id,
                            'row_number' => $csvRowNumber,
                            'sku'        => $data['sku'] ?? null,
                            'payload'    => $data,
                            'message'    => $e->getMessage(),
                        ]);
                    }

                    // progress log every 20 rows
                    if ($total % 5 === 0) {
                        Audit::info('Import progress', [
                            'job_id'  => $importJob->id,
                            'total'   => $total,
                            'success' => $success,
                            'failed'  => $failed,
                        ]);
                    }
                }
            }
        });

        // Job Perfomance Log
        $duration = round(microtime(true) - $startTime, 2);

        $importJob->update([
            'status' => ImportJob::STATUS_COMPLETED,
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ]);

        Audit::info("Product Import Completed", [
            'job_id' => $importJob->id,
            'duration_seconds' => $duration,
            'total_processed' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
        ]);
    }

    public function failed(Throwable $e): void
    {
        ImportJob::where('id', $this->importJobId)->update([
            'status' => ImportJob::STATUS_FAILED
        ]);

        Audit::info("Import job failed", [
            'job_id'  => $this->importJobId,
            'error' => $e->getMessage(),
        ]);
    }
}
