<?php

namespace Modules\AIAssistant\app\Knowledge;

/**
 * Fixed-size chunking with overlap — no ML required (brief §44: "no
 * premature over-engineering"). Splits on paragraph/sentence boundaries
 * where possible so a chunk doesn't cut a sentence in half mid-word.
 */
class TextChunker
{
    public function chunk(string $text, int $maxChars = 800, int $overlap = 100): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $maxChars) {
                $chunks[] = trim($current);
                // Carry the tail of the previous chunk forward as overlap,
                // so retrieval doesn't lose context sitting right at a
                // chunk boundary.
                $current = mb_substr($current, max(0, mb_strlen($current) - $overlap));
            }
            $current .= ($current === '' ? '' : ' ') . $sentence;
        }
        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
