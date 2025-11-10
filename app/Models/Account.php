<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_number',
        'balance',
        'currency',
        'status',
        'is_primary',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function debit(float $amount): bool
    {
        if (!$this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->decrement('balance', $amount);
        return true;
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
    }
}
