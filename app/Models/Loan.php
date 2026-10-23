<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasVersion4Uuids;

    protected $table = 'loan';
    public $timestamps = false;
    protected $fillable = ['member_id', 'loaned_by_user_id', 'loaned_at'];
    protected $hidden = ['loaned_by_user_id'];
    protected $casts = ['loaned_at' => 'datetime'];
    protected $appends = ['status'];

    public function items()
    {
        return $this->hasMany(LoanItem::class);
    }

    // CA-08.3: el estado se calcula, no se almacena.
    public function getStatusAttribute(): string
    {
        $open = $this->items->whereNull('returned_at');
        if ($open->isEmpty()) {
            return 'closed';
        }
        return $open->contains(fn ($i) => $i->due_at->isPast()) ? 'overdue' : 'open';
    }

    // Contrato §1: fechas ISO 8601 UTC con Z.
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s\Z');
    }
}