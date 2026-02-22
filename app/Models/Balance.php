<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Balance model representing user cryptocurrency balances.
 *
 * @property int $id
 * @property int $user_id ID of the associated user
 * @property string $currency_code Currency code (e.g., BTC, ETH, USDT)
 * @property string $amount Total balance amount (high precision decimal)
 * @property string $available_amount Available balance for operations
 * @property string $frozen_amount Frozen/locked balance (for pending orders, holds, etc.)
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 *
 * Relations
 * @property-read User $user The user that owns the balance
 * @property-read Collection<int, BalanceTransaction> $transactions
 *                   Collection of balance transactions
 * @property-read int $transactions_count
 */
class Balance extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'balances';

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = true;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'user_id',
		'currency_code',
		'amount',
		'available_amount',
		'frozen_amount',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'amount' => 'decimal:18',
		'available_amount' => 'decimal:18',
		'frozen_amount' => 'decimal:18',
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
	];
	private string $available_amount;
	private string $frozen_amount;

	/**
	 * Get the user that owns the balance.
	 */
	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	/**
	 * Get the transactions for the balance.
	 */
	public function transactions(): HasMany
	{
		return $this->hasMany(BalanceTransaction::class, 'balance_id');
	}

	/**
	 * Scope to get balances by currency code.
	 *
	 * @param Builder $query
	 * @param  string  $currencyCode
	 *
	 * @return Builder
	 */
	public function scopeByCurrency(Builder $query, string $currencyCode): Builder
	{
		return $query->where('currency_code', $currencyCode);
	}

	/**
	 * Scope to get balances with available amount greater than zero.
	 *
	 * @param Builder $query
	 *
	 * @return Builder
	 */
	public function scopeWithAvailableBalance(Builder $query): Builder
	{
		return $query->where('available_amount', '>', 0);
	}

	/**
	 * Check if balance has sufficient available funds.
	 *
	 * @param  float|string  $amount
	 * @return bool
	 */
	public function hasSufficientFunds($amount): bool
	{
		return bccomp((string)$this->available_amount, (string)$amount, 18) >= 0;
	}

	/**
	 * Freeze specified amount from available balance.
	 *
	 * @param  float|string  $amount
	 * @return bool
	 */
	public function freezeAmount($amount): bool
	{
		if (!$this->hasSufficientFunds($amount)) {
			return false;
		}

		$this->available_amount = bcsub((string)$this->available_amount, (string)$amount, 18);
		$this->frozen_amount = bcadd((string)$this->frozen_amount, (string)$amount, 18);

		return $this->save();
	}

	/**
	 * Unfreeze specified amount and return it to available balance.
	 *
	 * @param float|string $amount
	 *
	 * @return bool
	 */
	public function unfreezeAmount(float|string $amount): bool
	{
		$availableToUnfreeze = min((float)$this->frozen_amount, (float)$amount);

		$this->available_amount = bcadd(
			(string)$this->available_amount,
			(string)$availableToUnfreeze,
			18
		);
		$this->frozen_amount = bcsub(
			(string)$this->frozen_amount,
			(string)$availableToUnfreeze,
			18
		);

		return $this->save();
	}

	/**
	 * Update balance amounts (used after transaction processing).
	 *
	 * @param  array  $updates  ['amount' => $newAmount, 'available_amount' => $newAvailable]
	 * @return bool
	 */
	public function updateAmounts(array $updates): bool
	{
		foreach ($updates as $field => $value) {
			if (in_array($field, ['amount', 'available_amount', 'frozen_amount'])) {
				$this->{$field} = $value;
			}
		}

		return $this->save();
	}
}
