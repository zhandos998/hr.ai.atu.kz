<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CommissionCandidateUserSeeder;
use Database\Seeders\PpsCommissionSeeder;
use Database\Seeders\UserSeeder;
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

    public function test_kshit_faculty_commission_member_uses_seeded_user_password(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(PpsCommissionSeeder::class);

        $user = User::query()->where('email', 'b.pazylkhaiyr@atu.edu.kz')->firstOrFail();

        $this->assertSame('Пазылхайыр Бауыржан', $user->name);
        $this->assertTrue(Hash::check('8111', $user->password));
        $this->assertDatabaseHas('pps_faculty_commission_members', [
            'faculty_name' => 'Другое',
            'user_id' => $user->id,
        ]);
    }
}
