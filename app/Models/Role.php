<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'code',
        'description',
        'is_system_role',
    ];

    protected function casts(): array
    {
        return [
            'is_system_role' => 'boolean',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function isProtectedAdminRole(): bool
    {
        return $this->is_system_role && strcasecmp($this->name, 'Admin') === 0;
    }
}
