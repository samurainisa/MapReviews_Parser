<?php

namespace Tests\Feature;

use App\Enums\OrganizationParseStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Сквозной сценарий через fixtures (QUEUE=sync, YANDEX_PARSER_USE_FIXTURES=true
 * заданы в phpunit.xml): сохранение ссылки → job → синхронизация → API отзывов.
 */
class OrganizationParsingTest extends TestCase
{
    use RefreshDatabase;

    private string $cardUrl = 'https://yandex.ru/maps/org/demo/1234567890/';

    private function actingUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_rejects_non_yandex_url(): void
    {
        $this->actingUser();

        $this->postJson('/api/organization/settings', ['source_url' => 'https://example.com/place/1'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('source_url');
    }

    public function test_rejects_non_organization_yandex_url(): void
    {
        $this->actingUser();

        $this->postJson('/api/organization/settings', ['source_url' => 'https://yandex.ru/maps/?text=cafe'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('source_url');
    }

    public function test_saving_url_parses_and_stores_reviews(): void
    {
        $this->actingUser();

        $this->postJson('/api/organization/settings', ['source_url' => $this->cardUrl])
            ->assertStatus(202);

        $this->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('organization.parse_status', OrganizationParseStatus::Completed->value)
            ->assertJsonPath('organization.rating', 4.6)
            ->assertJsonPath('organization.reviews_count', 137)
            ->assertJsonPath('organization.loaded_reviews_count', 137);
    }

    public function test_reviews_are_paginated_by_50(): void
    {
        $this->actingUser();
        $this->postJson('/api/organization/settings', ['source_url' => $this->cardUrl]);

        $first = $this->getJson('/api/organization/reviews?page=1')->assertOk();
        $first->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 137)
            ->assertJsonPath('meta.last_page', 3);

        $this->getJson('/api/organization/reviews?page=3')
            ->assertOk()
            ->assertJsonCount(37, 'data');
    }

    public function test_reparsing_does_not_create_duplicates(): void
    {
        $this->actingUser();

        $this->postJson('/api/organization/settings', ['source_url' => $this->cardUrl]);
        $this->postJson('/api/organization/refresh')->assertStatus(202);

        $this->getJson('/api/organization')
            ->assertJsonPath('organization.loaded_reviews_count', 137);
        $this->assertSame(137, \App\Models\Review::count());
    }
}
