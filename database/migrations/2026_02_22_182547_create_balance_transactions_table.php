<?php

use App\Models\Balance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('balance_transactions', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->foreignIdFor(Balance::class, 'balance_id')
				->constrained()
				->restrictOnDelete();
			$table->enum('tx_type', ['deposit', 'withdrawal', 'transfer', 'fee', 'bonus']);
			$table->decimal('amount', 30, 18);
			$table->decimal('running_balance', 30, 18);
			$table->text('description')->nullable();
			$table->string('reference_id', 255)->nullable();
			$table->enum('status', ['pending', 'completed', 'failed', 'reversed'])
				->default('pending');
			$table->timestamp('created_at')->useCurrent();

			$table->index('balance_id', 'idx_balance_id');
			$table->index('tx_type', 'idx_tx_type');
			$table->index('status', 'idx_status');
			$table->index('created_at', 'idx_created_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('balance_transactions');
	}
};
