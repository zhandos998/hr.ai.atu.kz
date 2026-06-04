<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CommissionCandidateUserSeeder;
use Database\Seeders\PpsCommissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederPasswordPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pps_commission_seeder_does_not_reset_existing_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'b.esembaeva@atu.edu.kz',
            'password' => Hash::make('8363'),
        ]);

        $this->seed(PpsCommissionSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('8363', $user->password));
        $this->assertFalse(Hash::check('1234', $user->password));
    }

    public function test_commission_candidate_user_seeder_does_not_reset_existing_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'b.tolekova@atu.edu.kz',
            'password' => Hash::make('5677'),
        ]);

        $this->seed(CommissionCandidateUserSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('5677', $user->password));
        $this->assertFalse(Hash::check('1234', $user->password));
    }
}
