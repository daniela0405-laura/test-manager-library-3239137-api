<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasVersion4Uuids;

    protected $table = 'user';
    public $timestamps = false;
    protected $fillable = ['username', 'password_hash', 'role'];
    protected $hidden = ['password_hash']; // data-model §7: nunca sale en respuestas

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}