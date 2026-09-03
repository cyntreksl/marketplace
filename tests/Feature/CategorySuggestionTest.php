<?php

use App\Models\Category;
use App\Models\GoogleProductTaxonomyNode;
use App\Models\GoogleProductTaxonomyVersion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('category suggestions rank llm backed selectable leaf categories', function () {
    ['phones' => $phones, 'laptops' => $laptops] = createSuggestionCategories();
    config([
        'services.openai.api_key' => 'test-openai-key',
        'services.openai.category_suggestions.model' => 'gpt-4o-mini',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([
                'matches' => [
                    ['category_id' => $laptops->id, 'score' => 0.43, 'reason' => 'Could be a computer accessory.'],
                    ['category_id' => $phones->id, 'score' => 0.94, 'reason' => 'The title describes a smartphone.'],
                    ['category_id' => 999999, 'score' => 1, 'reason' => 'This id is not in the candidate set.'],
                ],
            ]),
        ]),
    ]);

    $this->getJson(route('categories.suggest', ['title' => 'Apple iPhone 15 phone', 'limit' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $phones->id)
        ->assertJsonPath('data.0.path', 'Electronics > Mobile Phones')
        ->assertJsonPath('data.0.score', 0.94)
        ->assertJsonPath('data.0.reason', 'The title describes a smartphone.')
        ->assertJsonStructure(['data' => [['id', 'name', 'path', 'slug', 'is_selectable', 'has_children', 'commission_percentage', 'score', 'reason']]]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
        && $request['model'] === 'gpt-4o-mini'
        && $request->hasHeader('Authorization', 'Bearer test-openai-key'));
});

test('category suggestions ignore non leaf model choices', function () {
    ['electronics' => $electronics, 'phones' => $phones] = createSuggestionCategories();
    config(['services.openai.api_key' => 'test-openai-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'output_text' => json_encode([
                'matches' => [
                    ['category_id' => $electronics->id, 'score' => 0.99, 'reason' => 'Too broad.'],
                    ['category_id' => $phones->id, 'score' => 0.89, 'reason' => 'Specific leaf match.'],
                ],
            ]),
        ]),
    ]);

    $this->getJson(route('categories.suggest', ['title' => 'Apple iPhone 15 phone']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $phones->id);
});

test('category suggestions fall back when llm output is malformed', function () {
    ['phones' => $phones] = createSuggestionCategories();
    config(['services.openai.api_key' => 'test-openai-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(['output_text' => 'not json']),
    ]);

    $this->getJson(route('categories.suggest', ['title' => 'Apple iPhone phone']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $phones->id)
        ->assertJsonPath('data.0.reason', 'Matched against available category names and paths.');
});

test('category suggestions validate title input', function () {
    $this->getJson(route('categories.suggest', ['title' => 'TV']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

/** @return array{electronics: Category, phones: Category, laptops: Category} */
function createSuggestionCategories(): array
{
    $taxonomy = GoogleProductTaxonomyVersion::factory()->create([
        'version' => 'suggestions',
        'locale' => 'en-US',
        'source_filename' => 'suggestions.txt',
        'checksum' => str_repeat('c', 64),
        'node_count' => 3,
        'is_active' => true,
    ]);
    $electronicsNode = GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'full_path' => 'Electronics',
        'depth' => 0,
    ]);
    GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 100,
        'parent_id' => $electronicsNode->id,
        'name' => 'Mobile Phones',
        'full_path' => 'Electronics > Mobile Phones',
        'depth' => 1,
    ]);
    GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 101,
        'parent_id' => $electronicsNode->id,
        'name' => 'Laptops',
        'full_path' => 'Electronics > Laptops',
        'depth' => 1,
    ]);

    $electronics = Category::factory()->create([
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_selectable' => false,
        'sort_order' => 1,
    ]);
    $phones = Category::factory()->create([
        'parent_id' => $electronics->id,
        'google_product_category_id' => 100,
        'name' => 'Mobile Phones',
        'slug' => 'mobile-phones',
        'is_selectable' => true,
        'sort_order' => 1,
    ]);
    $laptops = Category::factory()->create([
        'parent_id' => $electronics->id,
        'google_product_category_id' => 101,
        'name' => 'Laptops',
        'slug' => 'laptops',
        'is_selectable' => true,
        'sort_order' => 2,
    ]);

    return compact('electronics', 'phones', 'laptops');
}
