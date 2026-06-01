<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * วันหยุดราชการ — ดู .claude/rules/architecture.md "Master Data Cleanup Phase (V2)"
 */
class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'remark', 'source'];

    protected $casts = [
        'date' => 'date',
    ];
}
