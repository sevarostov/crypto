<?php

namespace App\Console\Commands;

use AllowDynamicProperties;
use App\Models\Balance;
use App\Services\BalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[AllowDynamicProperties] abstract class BaseBalanceCommand extends Command
{
	protected Balance $balance;
	protected int $balanceId;
	protected string $amount;
	protected string $txType;
	protected string $descriptionOption;
	protected string $referenceIdOption;

	public function __construct(protected readonly BalanceService $balanceService)
	{
		parent::__construct();
	}

	/**
	 * Validate and retrieve balance by ID.
	 *
	 * @param int $balanceId
	 *
	 * @return Balance|null
	 */
	protected function getValidBalance(int $balanceId): ?Balance
	{
		if ($balanceId <= 0) {
			$this->error('Balance ID must be a positive integer');
			return null;
		}


		if (!$this->balance = Balance::find($balanceId)) {
			$this->error("Balance with ID {$balanceId} not found");
			return null;
		}

		return $this->balance;
	}

	/**
	 * Validate amount (must be positive number with up to 18 decimal places).
	 *
	 * @param string $amount
	 *
	 * @return bool
	 */
	protected function validateAmount(string $amount): bool
	{
		if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
			$this->error('Amount must be a valid positive number with up to 18 decimal places');
			return false;
		}
		return true;
	}

	/**
	 * Validate transaction type against allowed types.
	 *
	 * @param string $txType
	 * @param array $allowedTypes
	 *
	 * @return bool
	 */
	protected function validateTransactionType(string $txType, array $allowedTypes): bool
	{
		if (!in_array($txType, $allowedTypes)) {
			$this->error("Invalid transaction type. Allowed: " . implode(', ', $allowedTypes));
			return false;
		}
		return true;
	}

	/**
	 * Display success message with transaction details.
	 *
	 * @param array $result
	 * @param Balance $balance
	 * @param string $operationName
	 */
	protected function displaySuccess(array $result, Balance $balance, string $operationName): void
	{
		$this->info("{$operationName} completed successfully!");
		$this->line('');
		$this->line('Details:');
		$this->line("Transaction ID: {$result['transaction']->id}");
		$this->line("Amount: {$this->argument('amount')} {$balance->currency_code}");
		$this->line("Type: {$this->argument('tx_type')}");

		$description = $this->option('description');
		if ($description) {
			$this->line("Description: {$description}");
		}

		$referenceId = $this->option('reference_id');
		if ($referenceId) {
			$this->line("Reference ID: {$referenceId}");
		}
	}

	/**
	 * Handle and display error, log it.
	 *
	 * @param array $result
	 * @param string $operationName
	 * @param int $balanceId
	 * @param string $amount
	 * @param string $txType
	 *
	 * @return int
	 */
	protected function handleError(array $result, string $operationName, int $balanceId, string $amount, string $txType): int
	{
		$errorMessage = "{$operationName} failed: " . $result['error'];
		$this->error($errorMessage);

		Log::error("Balance {$operationName} command failed", [
			'balance_id' => $balanceId,
			'amount' => $amount,
			'tx_type' => $txType,
			'error' => $result['error'],
		]);

		return self::FAILURE;
	}

	/**
	 * @var array
	 */
	protected array $transactionTypes;

	/**
	 * @return int
	 */
	protected function handle(): int
	{
		$this->balanceId = (int)$this->argument('balance_id');
		$this->amount = $this->argument('amount');
		$this->txType = $this->argument('tx_type');
		$this->descriptionOption = $this->option('description');
		$this->referenceIdOption = $this->option('reference_id');

		if (!$this->validateAmount($this->amount)) {
			return self::FAILURE;
		}

		if (!$this->validateTransactionType($this->txType, $this->transactionTypes)) {
			return self::FAILURE;
		}

		$balance = $this->getValidBalance($this->balanceId);
		if (!$balance) {
			return self::FAILURE;
		}

		return self::SUCCESS;
	}
}
