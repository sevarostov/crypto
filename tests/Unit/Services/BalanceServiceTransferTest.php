<?php

namespace Tests\Unit\Services;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use App\Models\User;
use App\Services\BalanceService;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceServiceTransferTest extends TestCase
{
	use RefreshDatabase;

	protected BalanceService $balanceService;
	protected Balance $senderBalance;
	protected Balance $recipientBalance;

	protected function setUp(): void
	{
		parent::setUp();
		$this->balanceService = new BalanceService();

		(new UserSeeder())->run();
		$users = User::all();

		$this->senderBalance = Balance::factory()->create([
			'amount' => '100.0000000000000000',
			'available_amount' => '80.0000000000000000',
			'frozen_amount' => '20.0000000000000000',
			'currency_code' => 'BTC',
		]);

		$this->recipientBalance = Balance::factory()->create([
			'amount' => '50.0000000000000000',
			'available_amount' => '50.0000000000000000',
			'frozen_amount' => '0.0000000000000000',
			'currency_code' => 'BTC',
		]);

	}

	/** @test */
	public function it_can_transfer_funds_successfully(): void
	{
		$amount = '25.0000000000000000';
		$description = 'Test transfer';

		$result = $this->balanceService->transfer(
			$this->senderBalance,
			$this->recipientBalance,
			$amount,
			$description,
		);

		$this->assertTrue($result['success']);
		$this->assertNull($result['error']);

		$this->senderBalance->refresh();
		$this->recipientBalance->refresh();
		// Verify balance updates
		$this->assertEquals('55.000000000000000000', $this->senderBalance->available_amount);
		$this->assertEquals('75.000000000000000000', $this->recipientBalance->available_amount);

		// Verify transactions were created
		$senderTransaction = $result['sender_transaction'];
		$recipientTransaction = $result['recipient_transaction'];

		$this->assertNotNull($senderTransaction);
		$this->assertNotNull($recipientTransaction);

		// Verify sender transaction
		$this->assertEquals($this->senderBalance->id, $senderTransaction->balance_id);
		$this->assertEquals('transfer', $senderTransaction->tx_type);
		$this->assertEquals('-25.000000000000000000', $senderTransaction->amount);
		$this->assertEquals('55.000000000000000000', $senderTransaction->running_balance);
		$this->assertEquals($description, $senderTransaction->description);
		$this->assertEquals('completed', $senderTransaction->status);

		// Verify recipient transaction
		$this->assertEquals($this->recipientBalance->id, $recipientTransaction->balance_id);
		$this->assertEquals('transfer', $recipientTransaction->tx_type);
		$this->assertEquals('25.000000000000000000', $recipientTransaction->amount);
		$this->assertEquals('75.000000000000000000', $recipientTransaction->running_balance);
		$this->assertEquals($description, $recipientTransaction->description);
		$this->assertEquals('completed', $recipientTransaction->status);
	}

	/** @test */
	public function it_fails_with_currency_mismatch(): void
	{
		// Create recipient with different currency
		$usdBalance = Balance::factory()->create([
			'currency_code' => 'USD',
			'available_amount' => '100.0000000000000000',
		]);

		$result = $this->balanceService->transfer(
			$this->senderBalance,
			$usdBalance,
			'25.0000000000000000',
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Currency mismatch: cannot transfer between different currencies',
			$result['error'],
		);
	}

	/** @test */
	public function it_maintains_atomicity_on_failure(): void
	{
		try {
			DB::transaction(function () {
				$this->balanceService->transfer(
					$this->senderBalance,
					$this->recipientBalance,
					'25.0000000000000000',
				);
				throw new \Exception('Simulated failure');
			});
		} catch (\Exception $e) {
			// Exception expected
		}

		// After simulated failure, balances should be unchanged
		$this->senderBalance->refresh();
		$this->recipientBalance->refresh();

		$this->assertEquals('80.000000000000000000', $this->senderBalance->available_amount);
		$this->assertEquals('50.000000000000000000', $this->recipientBalance->available_amount);

		// No transactions should have been created
		$this->assertEquals(0, BalanceTransaction::count());
	}



}
