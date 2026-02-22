<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
	/**
	 * Run the database seeders.
	 */
	public function run(): void
	{
		$users = [
			[
				'name' => 'Alice Johnson',
				'email' => 'alice.johnson@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Bob Smith',
				'email' => 'bob.smith@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Carol Davis',
				'email' => 'carol.davis@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'David Wilson',
				'email' => 'david.wilson@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Emma Brown',
				'email' => 'emma.brown@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Frank Miller',
				'email' => 'frank.miller@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Grace Lee',
				'email' => 'grace.lee@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Henry Taylor',
				'email' => 'henry.taylor@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Ivy Anderson',
				'email' => 'ivy.anderson@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Jack Thomas',
				'email' => 'jack.thomas@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Kate White',
				'email' => 'kate.white@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Liam Harris',
				'email' => 'liam.harris@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Mia Martin',
				'email' => 'mia.martin@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Noah Thompson',
				'email' => 'noah.thompson@example.com',
				'password' => Hash::make('password123'),
			],
			[
				'name' => 'Olivia Garcia',
				'email' => 'olivia.garcia@example.com',
				'password' => Hash::make('password123'),
			],
		];

		foreach ($users as $userData) {
			if (!User::where('name', $userData['name'])->count())
				User::create($userData);
		}
	}
}
