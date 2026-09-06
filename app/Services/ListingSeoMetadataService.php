<?php

namespace App\Services;

use App\Support\SeoText;
use Illuminate\Support\Str;

class ListingSeoMetadataService
{
    /** @return array{meta_title: string, meta_description: string} */
    public function generate(
        ?string $title,
        ?string $shortDescription,
        ?string $description,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
    ): array {
        return [
            'meta_title' => $this->firstText([$metaTitle, $title], 60),
            'meta_description' => $this->firstText([$metaDescription, $shortDescription, $description, $title], 160),
        ];
    }

    /** @param array<int, string|null> $candidates */
    private function firstText(array $candidates, int $limit): string
    {
        foreach ($candidates as $candidate) {
            $text = SeoText::plain($candidate ?? '');

            if ($text !== '') {
                return Str::limit($text, $limit, '');
            }
        }

        return '';
    }
}
