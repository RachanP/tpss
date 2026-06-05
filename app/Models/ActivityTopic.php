<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * V4 ข้อ 1 — หัวข้อกิจกรรมสำเร็จรูปต่อรายวิชา
 */
class ActivityTopic extends Model
{
    protected $fillable = ['course_id', 'name', 'sort_order'];

    protected $casts = [
        'course_id'  => 'integer',
        'sort_order' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
