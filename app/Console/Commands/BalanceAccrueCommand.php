<?php

namespace App\Console\Commands;

use App\Models\Balance;
use App\Services\BalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BalanceAccrueCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'balance:accrue
        {balance_id : ID of the balance to accrue funds to}
        {amount : Amount to deposit (with 18 decimal places)}
        {tx_type : Type of transaction (deposit or bonus)}
        {--description= : Description of the payment}
        {--reference_id= : External reference ID (e.g., payment gateway ID, bonus code)}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Accrue funds to a balance (deposit or bonus)';

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		$balanceId = (int)$this->argument('balance_id');
		$amount = $this->argument('amount');
		$txType = $this->argument('tx_type');
		$description = $this->option('description');
		$referenceId = $this->option('reference_id');

		if ($balanceId <= 0) {
			$this->error('Balance ID must be a positive integer');
			return self::FAILURE;
		}

		if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
			$this->error('Amount must be a valid positive number with up to 18 decimal places');
			return self::FAILURE;
		}

		$allowedTypes = ['deposit', 'bonus'];
		if (!in_array($txType, $allowedTypes)) {
			$this->error("Invalid transaction type. Allowed: " . implode(', ', $allowedTypes));
			return self::FAILURE;
		}

		$balance = Balance::find($balanceId);
		if (!$balance) {
			$this->error("Balance with ID {$balanceId} not found");
			return self::FAILURE;
		}

		$balanceService = app(BalanceService::class);
		$result = $balanceService->accrue($balance, $amount, $txType, $description, $referenceId);

		if ($result['success']) {
			$this->info('Accrual completed successfully!');
			$this->line('');
			$this->line('Details:');
			$this->line("Transaction ID: {$result['transaction']->id}");
			$this->line("Amount: {$amount} {$balance->currency_code}");
			$this->line("Type: {$txType}");
			if ($description) {
				$this->line("Description: {$description}");
			}
			if ($referenceId) {
				$this->line("Reference ID: {$referenceId}");
			}
			return self::SUCCESS;
		} else {
			$this->error('Accrual failed: ' . $result['error']);
			Log::error('Balance accrue command failed', [
				'balance_id' => $balanceId,
				'amount' => $amount,
				'tx_type' => $txType,
				'error' => $result['error'],
			]);
			return self::FAILURE;
		}
	}
}
