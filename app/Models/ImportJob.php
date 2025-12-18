<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_FAILED      = 'failed';

    protected $table = 'import_jobs';

    protected $fillable = [
        'filename',
        'status',
        'total',
        'success',
        'failed',
        'error',
    ];
}
