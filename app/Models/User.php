<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'employee_id', 'name', 'email', 'password', 'is_active', 'prefix'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['formatted_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }


    /**
     * Get the user's roles.
     */
    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Get the user's instructor profile (if applicable).
     */
    public function instructorProfile()
    {
        return $this->hasOne(InstructorProfile::class);
    }

    public function headOfDepartments()
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function secretaryOfDepartments()
    {
        return $this->hasMany(Department::class, 'secretary_user_id');
    }

    /**
     * Get formatted name with academic titles.
     */
    public function getFormattedNameAttribute()
    {
        $profile = $this->instructorProfile;
        $displayTitle = '';
        $userPrefix = $this->prefix;
        
        if ($profile && $profile->title) {
            $rawTitle = $profile->title;
            $titleMap = [
                'อาจารย์' => 'อ.',
                'ผู้ช่วยศาสตราจารย์' => 'ผศ.',
                'รองศาสตราจารย์' => 'รศ.',
                'ศาสตราจารย์' => 'ศ.',
                'ผู้ช่วยอาจารย์' => 'ผช.อ.',
                'ผู้ช่วยอาจารย์ (คลินิก)' => 'ผช.อ. (คลินิก)',
                'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)' => 'ผช.อ. (ปฏิบัติ)',
            ];
            $displayTitle = $titleMap[$rawTitle] ?? $rawTitle;
            
            if (str_contains($rawTitle, 'ผู้ช่วยอาจารย์')) {
                if ($profile->academic_degree === 'ปริญญาเอก') {
                    $displayTitle = 'ดร.';
                } else {
                    $displayTitle = ($userPrefix ?? '') ?: $displayTitle;
                }
            } else {
                if ($profile->academic_degree === 'ปริญญาเอก') {
                    if ($displayTitle === 'อ.') {
                        $displayTitle = 'อ.ดร.';
                    } else if (!str_contains($displayTitle, 'ดร.')) {
                        $displayTitle .= 'ดร.';
                    }
                }
            }
        } else {
            $displayTitle = $userPrefix ?? '';
        }
        $needsSpace = !empty($displayTitle) && !in_array($displayTitle, ['นาย', 'นาง', 'นางสาว', 'ดร.']) && !str_ends_with($displayTitle, 'ดร.');
        return $displayTitle . ($needsSpace ? ' ' : '') . $this->name;
    }
}
