<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_teacher_audit(): void
    {
        config([
            'services.teacher_audit.url' => 'https://kmk.atu.kz/api/external/teacher-audit',
            'services.teacher_audit.api_key' => 'test-key',
        ]);

        Http::fake([
            'kmk.atu.kz/*' => Http::response([
                'success' => true,
                'matches_found' => 1,
                'data' => [
                    [
                        'teacher_id' => 1354,
                        'teacher_name' => 'Хамитбек Аят Хайыржанұлы',
                        'department' => 'Машины и аппараты производственных процессов',
                        'faculty' => 'Факультет интеллектуальных и инженерных систем',
                        'dossier_url' => 'https://kmk.atu.kz/dossier/1354',
                        'violations' => [],
                        'audits' => [
                            [
                                'id' => 160,
                                'date' => '2026-03-27T14:42:59',
                                'type' => 'Регулярный',
                                'score' => 8.93,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/teacher-audit?name='.urlencode('Хамитбек Аят Хайыржанұлы'));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('matches_found', 1)
            ->assertJsonPath('data.0.teacher_id', 1354)
            ->assertJsonPath('data.0.audits.0.score', 8.93);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return $request->hasHeader('X-API-Key', 'test-key')
                && parse_url($request->url(), PHP_URL_HOST) === 'kmk.atu.kz'
                && parse_url($request->url(), PHP_URL_PATH) === '/api/external/teacher-audit'
                && ($query['name'] ?? null) === 'Хамитбек Аят Хайыржанұлы';
        });
    }
}
