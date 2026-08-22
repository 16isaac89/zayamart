<?php

namespace Modules\AIAssistant\app\Contracts;

/**
 * One extractor per file type (brief §14/§15) — KnowledgeIngestionService
 * picks by extension. Never dumps a raw file into the AI prompt; extracted
 * text is chunked and retrieved selectively instead (see
 * KnowledgeRetrievalService).
 */
interface TextExtractorInterface
{
    public function supports(string $extension): bool;

    /**
     * @throws \RuntimeException when extraction genuinely cannot happen
     *         (missing binary/library, corrupt file) — the caller marks the
     *         document failed with this message rather than pretending.
     */
    public function extract(string $absolutePath): string;
}
