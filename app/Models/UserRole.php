<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'user_roles';
    
    // Disable primary key as it's a composite key (user_id, role)
    protected $primaryKey = null;
    public $incrementing = false;
    
    // Only created_at is present
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'role',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
