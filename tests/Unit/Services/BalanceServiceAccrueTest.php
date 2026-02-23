<?php

namespace Tests\Unit\Services;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceServiceAccrueTest extends TestCase
{
	use RefreshDatabase;

	protected BalanceService $balanceService;
	protected Balance $balance;

	protected function setUp(): void
	{
		parent::setUp();
		$this->balanceService = new BalanceService();

		$this->balance = Balance::factory()->create([
			'amount' => '100.000000000000000000',
			'available_amount' => '80.000000000000000000',
			'frozen_amount' => '20.000000000000000000',
			'currency_code' => 'BTC',
		]);
	}

	/** @test */
	public function it_can_accrue_deposit_successfully(): void
	{
		$amount = '25.000000000000000000';
		$txType = 'deposit';
		$description = 'Bank transfer deposit';
		$referenceId = 'DEPOSIT-12345';

		$result = $this->balanceService->accrue(
			$this->balance,
			$amount,
			$txType,
			$description,
			$referenceId,
		);

		$this->assertTrue($result['success']);
		$this->assertNull($result['error']);

		$this->balance->refresh();

		$this->assertEquals('105.000000000000000000', $this->balance->available_amount);

		$transaction = $result['transaction'];
		$this->assertNotNull($transaction);

		$this->assertEquals($this->balance->id, $transaction->balance_id);
		$this->assertEquals($txType, $transaction->tx_type);
		$this->assertEquals($amount, $transaction->amount);
		$this->assertEquals('105.000000000000000000', $transaction->running_balance);
		$this->assertEquals($description, $transaction->description);
		$this->assertEquals($referenceId, $transaction->reference_id);
		$this->assertEquals('completed', $transaction->status);
	}

	/** @test */
	public function it_can_accrue_bonus_successfully(): void
	{
		$amount = '15.000000000000000000';
		$txType = 'bonus';
		$description = 'Welcome bonus';

		$result = $this->balanceService->accrue(
			$this->balance,
			$amount,
			$txType,
			$description,
		);

		$this->assertTrue($result['success']);

		$transaction = $result['transaction'];
		$this->assertNotNull($transaction);
		$this->assertEquals($txType, $transaction->tx_type);
		$this->assertEquals($amount, $transaction->amount);
		$this->assertEquals('95.000000000000000000', $transaction->running_balance);
		$this->assertEquals($description, $transaction->description);
	}

	/** @test */
	public function it_fails_with_negative_amount(): void
	{
		$result = $this->balanceService->accrue(
			$this->balance,
			'-10.000000000000000000',
			'deposit',
		);

		$this->assertFalse($result['success']);
		$this->assertNull($result['transaction']);
		$this->assertEquals(
			'Payment amount must be greater than zero',
			$result['error'],
		);
	}

	/** @test */
	public function it_fails_with_zero_amount(): void
	{
		$result = $this->balanceService->accrue(
			$this->balance,
			'0.000000000000000000',
			'bonus',
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Payment amount must be greater than zero',
			$result['error'],
		);
	}

	/** @test */
	public function it_fails_with_invalid_transaction_type(): void
	{
		$result = $this->balanceService->accrue(
			$this->balance,
			'50.000000000000000000',
			'withdrawal',
		);

		$this->assertFalse($result['success']);
		$this->assertEquals(
			'Invalid transaction type. Allowed: deposit, bonus',
			$result['error'],
		);
	}

	/** @test */
	public function it_handles_null_description_and_reference_id(): void
	{
		$result = $this->balanceService->accrue(
			$this->balance,
			'20.000000000000000000',
			'deposit',
		);

		$this->assertTrue($result['success']);

		$transaction = $result['transaction'];
		$this->assertNotNull($transaction);
		$this->assertStringContainsString('deposit of 20.000000000000000000 BTC', $transaction->description);
		$this->assertNull($transaction->reference_id);
	}

	/** @test */
	public function it_maintains_atomicity_on_failure(): void
	{
		try {
			DB::transaction(function () {
				$this->balanceService->accrue(
					$this->balance,
					'25.000000000000000000',
					'deposit',
				);
				throw new \Exception('Simulated database failure');
			});
		} catch (\Exception $e) {
			// Exception expected
		}

		$this->balance->refresh();
		$this->assertEquals('80.000000000000000000', $this->balance->available_amount);

		$this->assertEquals(0, BalanceTransaction::count());
	}

	/** @test */
	public function it_creates_transaction_with_correct_running_balance(): void
	{
		$initialAvailable = $this->balance->available_amount;
		$accrueAmount = '30.000000000000000000';

		$result = $this->balanceService->accrue(
			$this->balance,
			$accrueAmount,
			'bonus',
		);

		$this->assertTrue($result['success']);

		$transaction = $result['transaction'];
		$expectedRunningBalance = bcadd($initialAvailable, $accrueAmount, 18);

		$this->assertEquals($expectedRunningBalance, $transaction->running_balance);
	}

}
