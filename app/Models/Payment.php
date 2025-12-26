<?php

namespace App\Models;

use App\Models\User;
use App\Services\InvoiceNumberGeneratorService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'invoice_number',
        'name',
        'email',
        'phone',
        'price',
        'exchange_rate',
        'price_idr',
        'price_usd',
        'credit_points',
        'currency',
        'status',
        'due_date',
        'bank_name',
        'account_number',
        'account_name',
        'proof_image',
        'verified_at',
        'verified_by',
        'payment_method',
        'transaction_ref',
        'gateway_token',
        'gateway_payload',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'price' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'price_idr' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'credit_points' => 'decimal:2',
        'gateway_payload' => 'array',
    ];

    protected $attributes = [
        'currency' => 'IDR',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate invoice number when creating a new payment
        static::creating(function ($payment) {
            if (empty($payment->invoice_number)) {
                $payment->invoice_number = InvoiceNumberGeneratorService::generate();
            }
        });
    }

    /**
     * Scope a query to include payments that should be marked as expired.
     */
    public function scopeEligibleForExpiration($query)
    {
        return $query->whereIn('status', ['pending', 'waiting_verification'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    /**
     * Get the effective status of the payment, considering expiration.
     *
     * @return string
     */
    public function getCheckAndMarkAsExpiredAttribute()
    {
        // If payment is already paid, return the current status without any checks
        if ($this->status === 'paid') {
            return $this->status;
        }

        // Check if payment should be marked as expired
        if (
            in_array($this->status, ['pending', 'waiting_verification'], true)
            && $this->due_date !== null
            && now()->isAfter($this->due_date)
        ) {

            // Update the status to expired
            $this->forceFill([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

            $this->saveQuietly();
        }

        return $this->status;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
