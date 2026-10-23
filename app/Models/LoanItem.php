<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

class LoanItem extends Model
{
    use HasVersion4Uuids;

    protected $table = 'loan_item';
    public $timestamps = false;
    protected $fillable = ['loan_id', 'book_id', 'book_title', 'isbn', 'category_name', 'due_at', 'returned_at'];
    protected $hidden = ['loan_id'];
    protected $casts = ['due_at' => 'datetime', 'returned_at' => 'datetime'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s\Z');
    }
}