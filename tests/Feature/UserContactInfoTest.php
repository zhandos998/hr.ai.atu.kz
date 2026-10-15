<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserContactInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_public_contact_information(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/contact-info', [
            'phone' => '+7 700 000 00 00',
            'contact_info' => "Кабинет 214\nВнутренний номер 123",
        ])->assertOk()
            ->assertJsonPath('message', 'Контактная информация сохранена.')
            ->assertJsonPath('user.phone', '+7 700 000 00 00')
            ->assertJsonPath('user.contact_info', "Кабинет 214\nВнутренний номер 123");

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+7 700 000 00 00',
            'contact_info' => "Кабинет 214\nВнутренний номер 123",
        ]);
    }

    public function test_contact_information_is_limited_and_can_be_cleared(): void
    {
        $user = User::factory()->create(['contact_info' => 'Старые контакты']);
        Sanctum::actingAs($user);

        $this->putJson('/api/user/contact-info', [
            'contact_info' => str_repeat('А', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_info');

        $this->putJson('/api/user/contact-info', [
            'phone' => '',
            'contact_info' => '   ',
        ])->assertOk()
            ->assertJsonPath('user.phone', null)
            ->assertJsonPath('user.contact_info', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => null,
            'contact_info' => null,
        ]);
    }
}
