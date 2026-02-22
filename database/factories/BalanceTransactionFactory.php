<?php

namespace Database\Factories;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use Database\Seeders\BalanceSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<BalanceTransaction>
 */
class BalanceTransactionFactory extends Factory
{
	/**
	 * The name of the factory's corresponding model.
	 *
	 * @var string
	 */
	protected $model = BalanceTransaction::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$amount = (new BalanceSeeder())->generateDecimal(0.01, 1000, 18);

		return [
			'balance_id' => function () {
				// If balances exist, pick a random one; otherwise, create a new balance
				return Balance::inRandomOrder()->first()?->id ??
					Balance::factory()->create()->id;
			},
			'tx_type' => $this->getRandomTransactionType(),
			'amount' => $amount,
			'running_balance' => $this->calculateRunningBalance($amount),
			'description' => $this->faker->optional(0.7)->sentence(6),
			'reference_id' => $this->faker->optional(0.8)->uuid,
			'status' => $this->getRandomStatus(),
		];
	}

	/**
	 * Calculate running balance based on amount (add or subtract from base).
	 *
	 * @param string $amount Transaction amount
	 *
	 * @return string Running balance
	 */
	private function calculateRunningBalance(string $amount): string
	{
		$base = (new BalanceSeeder())->generateDecimal(500, 2000, 18);

		if ($this->faker->boolean(60)) {
			return bcadd($base, $amount, 18);
		} else {
			if (bccomp($base, $amount, 18) >= 0) {
				return bcsub($base, $amount, 18);
			} else {
				return $base;
			}
		}
	}

	/**
	 * Get a random transaction type.
	 *
	 * @return string Transaction type
	 */
	private function getRandomTransactionType(): string
	{
		$types = ['deposit', 'withdrawal', 'transfer', 'fee', 'bonus'];
		return $this->faker->randomElement($types);
	}

	/**
	 * Get a random transaction status.
	 *
	 * @return string Status
	 */
	private function getRandomStatus(): string
	{
		$statuses = ['pending', 'completed', 'failed'];
		return $this->faker->randomElement($statuses);
	}

	/**
	 * State for completed transactions.
	 */
	public function completed(): static
	{
		return $this->state(function (array $attributes): array {
			return ['status' => 'completed'];
		});
	}

	/**
	 * State for pending transactions.
	 */
	public function pending(): static
	{
		return $this->state(function (array $attributes): array {
			return ['status' => 'pending'];
		});
	}

	/**
	 * State for failed transactions.
	 */
	public function failed(): static
	{
		return $this->state(function (array $attributes): array {
			return ['status' => 'failed'];
		});
	}

	/**
	 * State for deposit transactions.
	 */
	public function deposit(): static
	{
		return $this->state(function (array $attributes): array {
			return ['tx_type' => 'deposit'];
		});
	}

	/**
	 * State for withdrawal transactions.
	 */
	public function withdrawal(): static
	{
		return $this->state(function (array $attributes): array {
			return ['tx_type' => 'withdrawal'];
		});
	}
}
