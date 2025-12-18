<?php

namespace App\Services\Product;

use App\Facades\Audit;
use App\Models\ImportJob;
use App\Jobs\ImportProductsJob;
use Illuminate\Support\Facades\DB;

class ProductImportService
{
    public function importProducts(string $filename): ImportJob
    {
        return DB::transaction(function () use ($filename) {

            $job = ImportJob::create([
                'filename' => $filename,
                'status' => ImportJob::STATUS_PENDING,
                'total' => 0,
                'success' => 0,
                'failed' => 0
            ]);

            ImportProductsJob::dispatch($job->id);

            Audit::info("Product import job dispatched", [
                'import_job_id' => $job->id,
                'filename' => $filename,
            ]);

            return $job;
        });
    }
}
