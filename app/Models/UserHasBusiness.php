<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserHasBusiness extends Model
{
    use HasFactory,HasPermissions;

    protected $fillable = [
        'business_id',
        'user_id',
    ];

    protected $guard_name = 'sanctum'; 
    protected $appends = ['business_name'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getBusinessNameAttribute(): string
    {
        return $this->business->name ?? '';
    }

    public static function userHasBusinessPermission($userId, $businessId, $permission)
    {
        // Get the user-business relationship
        $userHasBusiness = self::where('user_id', $userId)
                                ->where('business_id', $businessId)
                                ->first();

        // Check if the relationship exists and if the permission is assigned
        return $userHasBusiness && $userHasBusiness->hasPermissionTo($permission);
    }
}
