<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BalanceTransaction model representing cryptocurrency balance transactions.
 *
 * @property int $id
 * @property int $balance_id ID of the associated balance
 * @property string $tx_type Type of transaction (deposit, withdrawal, transfer, fee, bonus)
 * @property string $amount Transaction amount (high precision decimal)
 * @property string $running_balance Balance after this transaction was applied
 * @property string|null $description Description/notes about the transaction
 * @property string|null $reference_id External transaction reference ID (e.g., blockchain tx hash)
 * @property string $status Transaction status (pending, completed, failed, reversed)
 * @property Carbon $created_at
 *
 * Relations
 * @property-read Balance $balance The balance this transaction belongs to
 */
class BalanceTransaction extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'balance_transactions';

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false; // created_at is present, but no updated_at

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'balance_id',
		'tx_type',
		'amount',
		'running_balance',
		'description',
		'reference_id',
		'status',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'amount' => 'decimal:18',
		'running_balance' => 'decimal:18',
		'created_at' => 'datetime',
	];

	/**
	 * Get the balance that owns this transaction.
	 */
	public function balance(): BelongsTo
	{
		return $this->belongsTo(Balance::class);
	}

	/**
	 * Scope to get transactions by type.
	 *
	 * @param Builder $query
	 * @param string $type Transaction type (deposit, withdrawal, etc.)
	 *
	 * @return Builder
	 */
	public function scopeByType(Builder $query, string $type): Builder
	{
		return $query->where('tx_type', $type);
	}

	/**
	 * Scope to get transactions by status.
	 *
	 * @param Builder $query
	 * @param string $status Transaction status
	 *
	 * @return Builder
	 */
	public function scopeByStatus(Builder $query, string $status): Builder
	{
		return $query->where('status', $status);
	}

	/**
	 * Scope to get transactions for a specific balance.
	 *
	 * @param Builder $query
	 * @param int $balanceId ID of the balance
	 *
	 * @return Builder
	 */
	public function scopeForBalance(Builder $query, int $balanceId): Builder
	{
		return $query->where('balance_id', $balanceId);
	}

	/**
	 * Check if transaction is completed.
	 *
	 * @return bool True if status is 'completed'
	 */
	public function isCompleted(): bool
	{
		return $this->status === 'completed';
	}

	/**
	 * Check if transaction is pending.
	 *
	 * @return bool True if status is 'pending'
	 */
	public function isPending(): bool
	{
		return $this->status === 'pending';
	}

	/**
	 * Check if transaction has failed.
	 *
	 * @return bool True if status is 'failed'
	 */
	public function hasFailed(): bool
	{
		return $this->status === 'failed';
	}

	/**
	 * Mark transaction as completed.
	 *
	 * @return bool True on success
	 */
	public function markAsCompleted(): bool
	{
		$this->status = 'completed';
		return $this->save();
	}

	/**
	 * Mark transaction as failed.
	 *
	 * @param string|null $description Optional description for failure
	 *
	 * @return bool True on success
	 */
	public function markAsFailed(?string $description = null): bool
	{
		if ($description) {
			$this->description = $description;
		}
		$this->status = 'failed';
		return $this->save();
	}

	/**
	 * Reverse a completed transaction (creates a compensating transaction).
	 *
	 * @param string|null $reason Reason for reversal
	 *
	 * @return BalanceTransaction|null Reversed transaction or null on failure
	 */
	public function reverse(?string $reason = null): ?BalanceTransaction
	{
		if (!$this->isCompleted()) {
			return null;
		}

		// Create compensating transaction with opposite amount
		$reversal = $this->replicate();
		$reversal->tx_type = 'reversal';
		$reversal->amount = bcsub('0', (string)$this->amount, 18); // negative amount
		$reversal->status = 'completed';

		if ($reason) {
			$reversal->description = "Reversal: {$reason}";
		} else {
			$reversal->description = "Reversal of transaction #{$this->id}";
		}

		$reversal->save();

		// Update original transaction status
		$this->status = 'reversed';
		$this->save();

		return $reversal;
	}
}
