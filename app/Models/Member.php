<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasVersion4Uuids;

    protected $table = 'member';
    public $timestamps = false;
    protected $fillable = ['full_name', 'document_number'];
}