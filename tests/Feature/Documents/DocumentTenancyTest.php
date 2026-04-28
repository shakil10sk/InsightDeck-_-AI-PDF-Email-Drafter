<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_list_documents(): void
    {
        $this->getJson('/api/documents')->assertStatus(401);
    }

    public function test_users_only_see_their_own_documents(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Document::factory()->for($alice)->count(2)->create();
        Document::factory()->for($bob)->count(3)->create();

        $response = $this->actingAs($alice)->getJson('/api/documents');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $aliceIds = Document::query()->withoutGlobalScopes()->where('user_id', $alice->id)->pluck('id')->all();
        sort($ids);
        sort($aliceIds);
        $this->assertSame($aliceIds, $ids);
    }

    public function test_user_cannot_view_another_users_document(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobsDoc = Document::factory()->for($bob)->create();

        $this->actingAs($alice)
            ->getJson("/api/documents/{$bobsDoc->id}")
            ->assertStatus(404);
    }
}
