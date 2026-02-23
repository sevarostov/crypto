<?php

namespace App\Console\Commands;

use App\Models\Balance;
use App\Services\BalanceService;
use Illuminate\Support\Facades\Log;

class BalanceWriteOffCommand extends BaseBalanceCommand
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'balance:write-off
        {balance_id : ID of the balance to write off from}
        {amount : Amount to write off (with 18 decimal places)}
        {tx_type : Type of transaction (withdrawal or fee)}
        {--description= : Description of the write‑off}
        {--reference_id= : External reference ID (e.g., payment gateway ID, order ID)}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Write off funds from a balance (withdrawals, fees)';

	protected array $transactionTypes = ['withdrawal', 'fee'];

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		parent::handle();

		$result = $this->balanceService->writeOff(
			$this->balance,
			$this->amount,
			$this->txType,
			$this->descriptionOption,
			$this->referenceIdOption
		);

		if ($result['success']) {
			$this->displaySuccess($result, $this->balance, 'Write‑off');
			return self::SUCCESS;
		} else {
			return $this->handleError($result, 'Write‑off', $this->balance->id, $this->amount, $this->txType);
		}
	}
}
