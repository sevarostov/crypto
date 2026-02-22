<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceService
{
	/**
	 * Transfer funds from one balance to another.
	 *
	 * @param Balance $senderBalance The balance to send funds from
	 * @param Balance $recipientBalance The balance to receive funds
	 * @param string $amount The amount to transfer (with 18 decimal places)
	 * @param string|null $description Description of the transfer
	 * @return array{success: bool, sender_transaction: ?BalanceTransaction, recipient_transaction: ?BalanceTransaction, error: ?string}
	 */
	public function transfer(
		Balance $senderBalance,
		Balance $recipientBalance,
		string $amount,
		?string $description = null
	): array {
		if (bccomp($amount, '0', 18) <= 0) {
			return [
				'success' => false,
				'sender_transaction' => null,
				'recipient_transaction' => null,
				'error' => 'Transfer amount must be greater than zero'
			];
		}

		if (bccomp($senderBalance->available_amount, $amount, 18) < 0) {
			return [
				'success' => false,
				'sender_transaction' => null,
				'recipient_transaction' => null,
				'error' => 'Insufficient available funds for transfer'
			];
		}

		if ($senderBalance->currency_code !== $recipientBalance->currency_code) {
			return [
				'success' => false,
				'sender_transaction' => null,
				'recipient_transaction' => null,
				'error' => 'Currency mismatch: cannot transfer between different currencies'
			];
		}

		try {
			$result = DB::transaction(function () use ($senderBalance, $recipientBalance, $amount, $description) {

				$newSenderAvailable = bcsub($senderBalance->available_amount, $amount, 18);
				$newRecipientAvailable = bcadd($recipientBalance->available_amount, $amount, 18);

				$senderBalance->available_amount = $newSenderAvailable;
				$senderBalance->save();

				$recipientBalance->available_amount = $newRecipientAvailable;
				$recipientBalance->save();

				$senderTransaction = BalanceTransaction::create([
					'balance_id' => $senderBalance->id,
					'tx_type' => 'transfer',
					'amount' => bcsub('0', $amount, 18), // negative for outgoing
					'running_balance' => $newSenderAvailable,
					'description' => $description ?? 'Funds transfer to balance #' . $recipientBalance->id,
					'status' => 'completed',
				]);

				$recipientTransaction = BalanceTransaction::create([
					'balance_id' => $recipientBalance->id,
					'tx_type' => 'transfer',
					'amount' => $amount, // positive for incoming
					'running_balance' => $newRecipientAvailable,
					'description' => $description ?? 'Funds received from balance #' . $senderBalance->id,
					'status' => 'completed',
				]);

				return [
					'success' => true,
					'sender_transaction' => $senderTransaction,
					'recipient_transaction' => $recipientTransaction,
				];
			});

			return [
				'success' => $result['success'],
				'sender_transaction' => $result['sender_transaction'],
				'recipient_transaction' => $result['recipient_transaction'],
				'error' => null,
			];

		} catch (Exception $e) {
			Log::error('Transfer failed: ' . $e->getMessage());

			return [
				'success' => false,
				'sender_transaction' => null,
				'recipient_transaction' => null,
				'error' => 'Transfer failed due to system error: ' . $e->getMessage(),
			];
		}
	}
}
