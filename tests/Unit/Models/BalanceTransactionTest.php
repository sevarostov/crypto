<?php

namespace Tests\Unit\Models;

use App\Models\Balance;
use App\Models\BalanceTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceTransactionTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();
		$this->balance = Balance::factory()->create();
	}

	/** @test */
	public function it_can_be_instantiated(): void
	{
		$transaction = new BalanceTransaction();
		$this->assertInstanceOf(BalanceTransaction::class, $transaction);
	}

	/** @test */
	public function it_has_correct_table_name(): void
	{
		$transaction = new BalanceTransaction();
		$this->assertEquals('balance_transactions', $transaction->getTable());
	}

	/** @test */
	public function it_does_not_have_updated_at_timestamp(): void
	{
		$transaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '100.00',
			'running_balance' => '200.00',
			'status' => 'completed',
		]);

		$this->assertFalse(isset($transaction->updated_at));
	}

	/** @test */
	public function it_belongs_to_a_balance(): void
	{
		$transaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id
		]);

		$this->assertInstanceOf(Balance::class, $transaction->balance);
		$this->assertEquals($this->balance->id, $transaction->balance->id);
	}

	/** @test */
	public function it_can_scope_by_type(): void
	{
		BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '50.00',
			'running_balance' => '150.00',
			'status' => 'completed'
		]);

		BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'withdrawal',
			'amount' => '30.00',
			'running_balance' => '120.00',
			'status' => 'completed'
		]);

		$deposits = BalanceTransaction::byType('deposit')->get();
		$withdrawals = BalanceTransaction::byType('withdrawal')->get();

		$this->assertEquals(1, $deposits->count());
		$this->assertEquals(1, $withdrawals->count());
		$this->assertEquals('deposit', $deposits->first()->tx_type);
		$this->assertEquals('withdrawal', $withdrawals->first()->tx_type);
	}

	/** @test */
	public function it_can_scope_by_status(): void
	{
		BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '50.00',
			'running_balance' => '150.00',
			'status' => 'pending'
		]);

		BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '75.00',
			'running_balance' => '225.00',
			'status' => 'completed'
		]);

		$pending = BalanceTransaction::byStatus('pending')->get();
		$completed = BalanceTransaction::byStatus('completed')->get();

		$this->assertEquals(1, $pending->count());
		$this->assertEquals(1, $completed->count());
		$this->assertEquals('pending', $pending->first()->status);
		$this->assertEquals('completed', $completed->first()->status);
	}

	/** @test */
	public function it_can_scope_for_balance(): void
	{
		$otherBalance = Balance::factory()->create();

		BalanceTransaction::factory()->count(2)->create([
			'balance_id' => $this->balance->id
		]);

		BalanceTransaction::factory()->create([
			'balance_id' => $otherBalance->id
		]);

		$transactions = BalanceTransaction::forBalance($this->balance->id)->get();

		$this->assertEquals(2, $transactions->count());
		foreach ($transactions as $transaction) {
			$this->assertEquals($this->balance->id, $transaction->balance_id);
		}
	}

	/** @test */
	public function it_correctly_identifies_completed_transactions(): void
	{
		$completedTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '100.00',
			'running_balance' => '200.00',
			'status' => 'completed'
		]);

		$notCompletedTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '50.00',
			'running_balance' => '150.00',
			'status' => 'pending'
		]);

		$this->assertTrue($completedTransaction->isCompleted());
		$this->assertFalse($notCompletedTransaction->isCompleted());
	}

	/** @test */
	public function it_correctly_identifies_pending_transactions(): void
	{
		$pendingTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '100.00',
			'running_balance' => '200.00',
			'status' => 'pending'
		]);

		$notPendingTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '50.00',
			'running_balance' => '150.00',
			'status' => 'completed'
		]);

		$this->assertTrue($pendingTransaction->isPending());
		$this->assertFalse($notPendingTransaction->isPending());
	}

	/** @test */
	public function it_correctly_identifies_failed_transactions(): void
	{
		$failedTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '100.00',
			'running_balance' => '200.00',
			'status' => 'failed'
		]);

		$notFailedTransaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '50.00',
			'running_balance' => '150.00',
			'status' => 'completed'
		]);

		$this->assertTrue($failedTransaction->hasFailed());
		$this->assertFalse($notFailedTransaction->hasFailed());
	}

	/** @test */
	public function it_can_mark_transaction_as_completed(): void
	{
		// Arrange: create a transaction with 'pending' status
		$transaction = BalanceTransaction::factory()->create([
			'balance_id' => $this->balance->id,
			'tx_type' => 'deposit',
			'amount' => '100.00',
			'running_balance' => '200.00',
			'status' => 'pending'
		]);

		// Ensure the initial status is 'pending' before the operation
		$this->assertEquals('pending', $transaction->status);
		$this->assertFalse($transaction->isCompleted());

		// Act: call the method to mark as completed
		$result = $transaction->markAsCompleted();

		// Assert: check that the method returned true (success)
		$this->assertTrue($result);

		// Reload the model from the database to verify the change
		$transaction->refresh();

		// Verify that the status has been updated to 'completed'
		$this->assertEquals('completed', $transaction->status);
		$this->assertTrue($transaction->isCompleted());

		// Optional: verify the database record directly
		$dbTransaction = BalanceTransaction::find($transaction->id);
		$this->assertNotNull($dbTransaction);
		$this->assertEquals('completed', $dbTransaction->status);
	}

}
