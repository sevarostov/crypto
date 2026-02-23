<?php

namespace Tests\Unit\Services;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use App\Services\BalanceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceServiceWriteOffTest extends TestCase
{
	use RefreshDatabase;

	protected BalanceService $balanceService;
	protected Balance $balance;

	protected function setUp(): void
	{
		parent::setUp();
		$this->balanceService = new BalanceService();

		$this->balance = Balance::factory()->create([
			'amount' => '200.000000000000000000',
			'available_amount' => '150.000000000000000000',
			'frozen_amount' => '50.000000000000000000',
			'currency_code' => 'BTC',
		]);
	}

	/** @test */
	public function it_can_write_off_funds_successfully(): void
	{
		$amount = '25.000000000000000000';
		$txType = 'withdrawal';
		$description = 'ATM withdrawal';
		$referenceId = 'TXN-12345';

		$result = $this->balanceService->writeOff(
			$this->balance,
			$amount,
			$txType,
			$description,
			$referenceId
		);

		// Assert success
		$this->assertTrue($result['success']);
		$this->assertNull($result['error']);

		// Refresh balance from DB
		$this->balance->refresh();

		// Verify balance update
		$this->assertEquals('125.000000000000000000', $this->balance->available_amount);

		// Verify transaction was created
		$transaction = $result['transaction'];
		$this->assertNotNull($transaction);

		// Verify transaction details
		$this->assertEquals($this->balance->id, $transaction->balance_id);
		$this->assertEquals($txType, $transaction->tx_type);
		$this->assertEquals('-25.000000000000000000', $transaction->amount);
		$this->assertEquals('125.000000000000000000', $transaction->running_balance);
		$this->assertEquals($description, $transaction->description);
		$this->assertEquals($referenceId, $transaction->reference_id);
		$this->assertEquals('completed', $transaction->status);
	}

	/** @test */
	public function it_fails_with_negative_amount(): void
	{
		$result = $this->balanceService->writeOff(
			$this->balance,
			'-10.000000000000000000',
			'withdrawal'
		);

		$this->assertFalse($result['success']);
		$this->assertNull($result['transaction']);
		$this->assertEquals(
			'Write‑off amount must be greater than zero',
			$result['error']
		);
	}

	/** @test */
	public function it_fails_with_zero_amount(): void
	{
		$result = $this->balanceService->writeOff(
			$this->balance,
			'0.000000000000000000',
			'payment'
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Write‑off amount must be greater than zero',
			$result['error']
		);
	}

	/** @test */
	public function it_fails_with_insufficient_funds(): void
	{
		// Try to write off more than available
		$result = $this->balanceService->writeOff(
			$this->balance,
			'200.000000000000000000', // More than 150 available
			'withdrawal'
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Insufficient available funds for write‑off',
			$result['error']
		);

		// Balance should remain unchanged
		$this->balance->refresh();
		$this->assertEquals('150.000000000000000000', $this->balance->available_amount);
	}

	/** @test */
	public function it_fails_with_invalid_transaction_type(): void
	{
		$result = $this->balanceService->writeOff(
			$this->balance,
			'50.000000000000000000',
			'invalid_type'
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Invalid transaction type. Allowed: withdrawal, fee',
			$result['error']
		);
	}

	/** @test */
	public function it_handles_null_description_and_reference_id(): void
	{
		$result = $this->balanceService->writeOff(
			$this->balance,
			'10.000000000000000000',
			'fee'
		);

		$this->assertTrue($result['success']);

		$transaction = $result['transaction'];
		$this->assertNotNull($transaction);
		$this->assertStringContainsString('Write‑off of 10.000000000000000000 BTC', $transaction->description);
		$this->assertNull($transaction->reference_id);
	}

	/** @test */
	public function it_maintains_atomicity_on_failure(): void
	{
		try {
			DB::transaction(function () {
				$this->balanceService->writeOff(
					$this->balance,
					'25.000000000000000000',
					'withdrawal'
				);
				throw new \Exception('Simulated database failure');
			});
		} catch (\Exception $e) {
			// Exception expected
		}

		// After simulated failure, balance should be unchanged
		$this->balance->refresh();
		$this->assertEquals('150.000000000000000000', $this->balance->available_amount);

		// No transactions should have been created
		$this->assertEquals(0, BalanceTransaction::count());
	}

	/** @test */
	public function it_creates_transaction_with_correct_running_balance(): void
	{
		$initialAvailable = $this->balance->available_amount;
		$writeOffAmount = '30.000000000000000000';

		$result = $this->balanceService->writeOff(
			$this->balance,
			$writeOffAmount,
			'fee'
		);

		$this->assertTrue($result['success']);

		$transaction = $result['transaction'];
		$expectedRunningBalance = bcsub($initialAvailable, $writeOffAmount, 18);

		$this->assertEquals($expectedRunningBalance, $transaction->running_balance);
	}
}
