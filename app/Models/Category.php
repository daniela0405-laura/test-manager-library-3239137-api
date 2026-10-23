<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasVersion4Uuids;

    protected $table = 'category';
    public $timestamps = false;
    protected $fillable = ['name'];
}