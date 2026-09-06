<?php

namespace App\Mcp\Tools;

use App\Services\CategorySuggestionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-product-categories')]
#[Title('Search Product Categories')]
#[Description('Finds the best selectable marketplace category IDs for a product title. Call this before create-draft-product when the category ID is unknown.')]
#[IsReadOnly]
#[IsOpenWorld]
class SearchProductCategoriesTool extends Tool
{
    public function __construct(private readonly CategorySuggestionService $categories) {}

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Product title or concise product description to categorize.')
                ->max(160)
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of category suggestions to return.')
                ->min(1)
                ->max(8)
                ->default(5),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'between:1,8'],
        ]);

        $suggestions = $this->categories
            ->suggest((string) $validated['title'], (int) ($validated['limit'] ?? 5))
            ->map(fn (array $category): array => [
                'category_id' => $category['id'],
                'name' => $category['name'],
                'path' => $category['path'],
                'confidence_score' => $category['score'],
                'reason' => $category['reason'],
            ])
            ->values()
            ->all();

        return Response::json([
            'suggestions' => $suggestions,
            'message' => $suggestions === []
                ? 'No selectable product categories matched this title.'
                : 'Use the category_id from the best matching suggestion when creating the draft product.',
        ]);
    }
}
