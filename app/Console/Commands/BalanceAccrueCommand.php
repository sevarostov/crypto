<?php

namespace App\Console\Commands;


class BalanceAccrueCommand extends BaseBalanceCommand
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

	protected array $transactionTypes = ['deposit', 'bonus'];

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
		parent::handle();

		$result = $this->balanceService->accrue(
			$this->balance,
			$this->amount,
			$this->txType,
			$this->descriptionOption,
			$this->referenceIdOption
		);

		if ($result['success']) {
			$this->displaySuccess($result, $this->balance, 'Accrual');
			return self::SUCCESS;
		} else {
			return $this->handleError($result, 'Accrual', $this->balance->id, $this->amount, $this->txType);
		}
	}
}
