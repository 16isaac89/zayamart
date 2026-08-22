<?php

namespace Modules\AIAssistant\app\Contracts;

/**
 * Extension point for real vector embeddings (brief §15) — not wired to a
 * live provider in this release; retrieval uses MariaDB FULLTEXT search
 * instead (see KnowledgeRetrievalService). Deliberately not coupled to any
 * one embedding vendor, so OpenAI/Gemini/local embeddings can be added
 * later without touching the ingestion pipeline or retrieval call sites.
 */
interface KnowledgeEmbeddingProviderInterface
{
    /**
     * @return float[] empty array means "no embedding available" — callers
     *         must treat that as a valid, expected state, not an error.
     */
    public function embed(string $text): array;
}
