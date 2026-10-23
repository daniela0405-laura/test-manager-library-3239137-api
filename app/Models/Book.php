<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasVersion4Uuids, SoftDeletes; // CA-03.5: baja lógica

    protected $table = 'book';
    public $timestamps = false;
    protected $fillable = ['title', 'author', 'isbn', 'category_id', 'total_copies', 'available_copies', 'cover_key'];
    protected $hidden = ['deleted_at', 'category_id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}