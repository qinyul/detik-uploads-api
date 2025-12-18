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


    public function handle(): void
    {
        $startTime = microtime(true);
        $importJob = ImportJob::findOrFail($this->importJobId);

        Audit::info("Starting Product Import", [
            'job_id' => $importJob->id,
            'file' => $importJob->filename
        ]);

        $importJob->update(['status' => ImportJob::STATUS_IN_PROGRESS]);

        $path = Storage::path($importJob->filename);
        $rows = array_map('str_getcsv', file($path));
        $header = array_shift($rows);

        $rows = array_filter($rows, function ($row) {
            return $row != null && reset($row) != null;
        });

        $total = count($rows);
        $success = 0;
        $failed = 0;

        DB::transaction(function () use (
            $rows,
            $header,
            $importJob,
            &$success,
            &$failed,
        ) {
            foreach ($rows as $index => $row) {
                // real csv row index + 2 because of header + index 0 start
                $csvRowNumber = $index + 2;
                try {
                    $data = array_combine($header, $row);

                    Product::create([
                        'name' => $data['name'],
                        'sku' => $data['sku'],
                        'price' => $data['price'],
                        'stock' => $data['stock']
                    ]);

                    $success++;
                } catch (Throwable $e) {
                    $failed++;
                    $sku = $data['sku'] ?? 'UNKNOWN';

                    Audit::info("Import failed at Row {$csvRowNumber}", [
                        'job_id' => $importJob->id,
                        'sku' => $sku,
                        'errror' => $e->getMessage()
                    ]);
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
        Audit::info("Import job failed", [
            'error' => $e->getMessage(),
        ]);

        ImportJob::where('id', $this->importJobId)->update([
            'status' => ImportJob::STATUS_FAILED
        ]);
    }
}
