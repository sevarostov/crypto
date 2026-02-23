<?php

namespace App\Console\Commands;

use App\Models\Balance;
use App\Services\BalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BalanceTransferCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'balance:transfer
        {sender_balance_id : ID of the sender balance}
        {recipient_balance_id : ID of the recipient balance}
        {amount : Amount to transfer (with 18 decimal places)}
        {--description= : Description of the transfer}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Transfer funds between two balances';

	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
		$senderBalanceId = (int)$this->argument('sender_balance_id');
		$recipientBalanceId = (int)$this->argument('recipient_balance_id');
		$amount = $this->argument('amount');
		$description = $this->option('description');

		if ($senderBalanceId <= 0) {
			$this->error('Sender balance ID must be a positive integer');
			return self::FAILURE;
		}

		if ($recipientBalanceId <= 0) {
			$this->error('Recipient balance ID must be a positive integer');
			return self::FAILURE;
		}

		if (!is_numeric($amount) || bccomp($amount, '0', 18) <= 0) {
			$this->error('Amount must be a valid positive number with up to 18 decimal places');
			return self::FAILURE;
		}

		$senderBalance = Balance::find($senderBalanceId);
		if (!$senderBalance) {
			$this->error("Sender balance with ID {$senderBalanceId} not found");
			return self::FAILURE;
		}

		$recipientBalance = Balance::find($recipientBalanceId);
		if (!$recipientBalance) {
			$this->error("Recipient balance with ID {$recipientBalanceId} not found");
			return self::FAILURE;
		}

		$balanceService = app(BalanceService::class);
		$result = $balanceService->transfer($senderBalance, $recipientBalance, $amount, $description);

		if ($result['success']) {
			$this->info('Transfer completed successfully!');
			$this->line('');
			$this->line('Details:');
			$this->line("Sender transaction ID: {$result['sender_transaction']->id}");
			$this->line("Recipient transaction ID: {$result['recipient_transaction']->id}");
			$this->line("Amount: {$amount} {$senderBalance->currency_code}");
			return self::SUCCESS;
		} else {
			$this->error('Transfer failed: ' . $result['error']);
			Log::error('Balance transfer command failed', [
				'sender_balance_id' => $senderBalanceId,
				'recipient_balance_id' => $recipientBalanceId,
				'amount' => $amount,
				'error' => $result['error'],
			]);
			return self::FAILURE;
		}
	}
}
