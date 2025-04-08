<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\UserHasBusiness;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'u_code',
        'email',
        'cnic',
        'city_id',
        'password',
        'setup_code',
        'setup_code_expiry',
        'cnic_images',
        'avatar',
        'login_business',
        'is_verify',
        'role',
        'last_login',
        'ip',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function hasBusinessPermission($businessId, $permission)
    {
        // Get the user-business relationship
        $userHasBusiness = UserHasBusiness::where('user_id', $this->id)
                                           ->where('business_id', $businessId)
                                           ->first();

        // Check if the relationship exists and if the permission is assigned
        return $userHasBusiness && $userHasBusiness->hasPermissionTo($permission);
    }

    public function businesses()
    {
        return $this->hasMany(UserHasBusiness::class);
    }
}
