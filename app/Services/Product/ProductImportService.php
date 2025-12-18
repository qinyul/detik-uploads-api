<?php

namespace App\Services\Product;

use App\Facades\Audit;
use App\Models\ImportJob;
use App\Jobs\ImportProductsJob;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

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

    public function getStatus(int $jobId): ImportJob
    {
        $job = ImportJob::query()
            ->select(['id as job_id', 'status', 'total', 'success', 'failed', 'updated_at'])
            ->find($jobId);

        if (is_null($job)) {
            Audit::warning("failed to find job", [
                'job_id' => $jobId
            ]);
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404));
        }
        return $job;
    }
}
