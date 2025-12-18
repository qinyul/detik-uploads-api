<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportProductRequest;
use App\Services\Product\ProductImportService;
use Illuminate\Http\JsonResponse;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductImportService $importService
    ) {}

    public function import(ImportProductRequest $request)
    {
        $file = $request->file('file');
        $path = $file->store('imports');

        $job = $this->importService->importProducts($path);

        return response()->json([
            'job_id' => $job->id,
            'status' => $job->status,
        ], 202);
    }

    public function show(int $jobId): JsonResponse
    {
        $job = $this->importService->getStatus($jobId);

        return response()->json($job);
    }
}
