<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Balance;
use App\Models\User;

class BalanceSeeder extends Seeder
{
	/**
	 * Run the database seeders for balances.
	 */
	public function run(): void
	{
		$users = User::all();

		$currencies = ['BTC', 'ETH', 'USDT'];

		foreach ($users as $user) {
			foreach ($currencies as $currency) {

				$totalAmount = $this->generateDecimal(0, 1000, 18);

				$percentage = rand(0, 100) / 100; // 0.00 to 1.00
				$availableAmount = bcmul($totalAmount, (string)$percentage, 18);

				$frozenAmount = bcsub($totalAmount, $availableAmount, 18);

				Balance::create([
					'user_id' => $user->id,
					'currency_code' => $currency,
					'amount' => $totalAmount,
					'available_amount' => $availableAmount,
					'frozen_amount' => $frozenAmount,
				]);
			}
		}
	}

	/**
	 * Generate a decimal number as a string in the format X.XXXXXXXXXXXXXXXXXX
	 * with specified decimal places.
	 *
	 * @param float|int $min Minimum value
	 * @param float|int $max Maximum value
	 * @param int $decimalPlaces Number of decimal places (default: 18)
	 *
	 * @return string Decimal number as string
	 */
	public function generateDecimal(float|int $min, float|int $max, int $decimalPlaces = 18): string
	{
		$scaledMin = (int)($min * 100);
		$scaledMax = (int)($max * 100);

		$randomScaled = rand($scaledMin, $scaledMax);

		return number_format($randomScaled / 100, $decimalPlaces, '.', '');
	}
}
