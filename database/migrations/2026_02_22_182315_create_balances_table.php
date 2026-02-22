<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('balances', function (Blueprint $table) {
			$table->bigIncrements('id');
			$table->foreignIdFor(User::class, 'user_id')
				->constrained()
				->cascadeOnDelete();
			$table->char('currency_code', 10);
			$table->decimal('amount', 30, 18)->default(0.00);
			$table->decimal('available_amount', 30, 18)->default(0.00);
			$table->decimal('frozen_amount', 30, 18)->default(0.00);
			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')
				->useCurrent()
				->useCurrentOnUpdate();

			$table->index('user_id', 'idx_user_id');
			$table->index('currency_code', 'idx_currency_code');
			$table->unique(['user_id', 'currency_code'], 'unique_user_currency');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('balances');
	}
};
