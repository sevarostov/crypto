<?php

namespace Database\Factories;

use App\Models\Balance;
use App\Models\User;
use Database\Seeders\BalanceSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Balance>
 */
class BalanceFactory extends Factory
{
	/**
	 * The name of the factory's corresponding model.
	 *
	 * @var string
	 */
	protected $model = Balance::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		// Generate random amounts with 18 decimal places
		$totalAmount = (new BalanceSeeder())->generateDecimal(0, 10000, 18);
		$availableAmount = (new BalanceSeeder())->generateDecimal(0, (float)$totalAmount, 18);

		// Calculate frozen amount using BCMath to maintain precision
		$frozenAmount = bcsub($totalAmount, $availableAmount, 18);

		return [
			'user_id' => function () {
				return User::inRandomOrder()->first()?->id ??
					User::factory()->create()->id;
			},
			'currency_code' => $this->getRandomCurrency(),
			'amount' => $totalAmount,
			'available_amount' => $availableAmount,
			'frozen_amount' => $frozenAmount,
		];
	}

	/**
	 * Get a random cryptocurrency currency code.
	 *
	 * @return string Currency code
	 */
	private function getRandomCurrency(): string
	{
		$currencies = ['BTC', 'ETH', 'USDT'];
		return $this->faker->randomElement($currencies);
	}
}
