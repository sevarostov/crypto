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
	 * Handle incoming payments (deposits, bonuses) to a balance.
	 *
	 * @param Balance $balance The balance to receive funds
	 * @param string $amount The amount to deposit (with 18 decimal places)
	 * @param string $txType Type of transaction ('deposit' or 'bonus')
	 * @param string|null $description Description of the payment
	 * @param string|null $referenceId External reference ID (e.g., payment gateway ID, bonus code)
	 *
	 * @return array{success: bool, transaction: ?BalanceTransaction, error: ?string}
	 */
	public function accrue(
		Balance $balance,
		string $amount,
		string $txType,
		?string $description = null,
		?string $referenceId = null,
	): array
	{

		if (bccomp($amount, '0', 18) <= 0) {
			return [
				'success' => false,
				'transaction' => null,
				'error' => 'Payment amount must be greater than zero'
			];
		}

		$allowedTypes = ['deposit', 'bonus'];
		if (!in_array($txType, $allowedTypes)) {
			return [
				'success' => false,
				'transaction' => null,
				'error' => "Invalid transaction type. Allowed: " . implode(', ', $allowedTypes)
			];
		}

		try {
			$result = DB::transaction(function () use ($balance, $amount, $txType, $description, $referenceId) {

				$newAvailable = bcadd($balance->available_amount, $amount, 18);

				$balance->available_amount = $newAvailable;
				$balance->save();

				$transaction = BalanceTransaction::create([
					'balance_id' => $balance->id,
					'tx_type' => $txType,
					'amount' => $amount, // positive for incoming
					'running_balance' => $newAvailable,
					'description' => $description ?? "{$txType} of {$amount} {$balance->currency_code}",
					'reference_id' => $referenceId,
					'status' => 'completed',
				]);

				return [
					'success' => true,
					'transaction' => $transaction,
				];
			});

			return [
				'success' => $result['success'],
				'transaction' => $result['transaction'],
				'error' => null,
			];

		} catch (Exception $e) {
			Log::error('Incoming payment failed: ' . $e->getMessage());

			return [
				'success' => false,
				'transaction' => null,
				'error' => 'Incoming payment failed due to system error: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Write off funds from a balance (withdrawals, payments, commissions).
	 *
	 * @param Balance $balance The balance to write off from
	 * @param string $amount The amount to write off (with 18 decimal places)
	 * @param string $txType Type of transaction ('withdrawal', 'fee')
	 * @param string|null $description Description of the write‑off
	 * @param string|null $referenceId External reference ID (e.g., payment gateway ID, order ID)
	 *
	 * @return array{success: bool, transaction: ?BalanceTransaction, error: ?string}
	 */
	public function writeOff(
		Balance $balance,
		string $amount,
		string $txType,
		?string $description = null,
		?string $referenceId = null,
	): array
	{

		if (bccomp($amount, '0', 18) <= 0) {
			return [
				'success' => false,
				'transaction' => null,
				'error' => 'Write‑off amount must be greater than zero'
			];
		}

		if (bccomp($balance->available_amount, $amount, 18) < 0) {
			return [
				'success' => false,
				'transaction' => null,
				'error' => 'Insufficient available funds for write‑off'
			];
		}

		$allowedTypes = ['withdrawal', 'fee'];
		if (!in_array($txType, $allowedTypes)) {
			return [
				'success' => false,
				'transaction' => null,
				'error' => "Invalid transaction type. Allowed: " . implode(', ', $allowedTypes)
			];
		}

		try {
			$result = DB::transaction(function () use ($balance, $amount, $txType, $description, $referenceId) {
				$newAvailable = bcsub($balance->available_amount, $amount, 18);

				$balance->available_amount = $newAvailable;
				$balance->save();

				$transaction = BalanceTransaction::create([
					'balance_id' => $balance->id,
					'tx_type' => $txType,
					'amount' => bcsub('0', $amount, 18), // negative for outgoing
					'running_balance' => $newAvailable,
					'description' => $description ?? "Write‑off of {$amount} {$balance->currency_code}",
					'reference_id' => $referenceId,
					'status' => 'completed',
				]);

				return [
					'success' => true,
					'transaction' => $transaction,
				];
			});

			return [
				'success' => $result['success'],
				'transaction' => $result['transaction'],
				'error' => null,
			];

		} catch (Exception $e) {
			Log::error('Write‑off failed: ' . $e->getMessage());

			return [
				'success' => false,
				'transaction' => null,
				'error' => 'Write‑off failed due to system error: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Transfer funds from one balance to another.
	 *
	 * @param Balance $senderBalance The balance to send funds from
	 * @param Balance $recipientBalance The balance to receive funds
	 * @param string $amount The amount to transfer (with 18 decimal places)
	 * @param string|null $description Description of the transfer
	 *
	 * @return array{success: bool, sender_transaction: ?BalanceTransaction, recipient_transaction:
	 *     ?BalanceTransaction, error: ?string}
	 */
	public function transfer(
		Balance $senderBalance,
		Balance $recipientBalance,
		string $amount,
		?string $description = null,
	): array
	{
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
