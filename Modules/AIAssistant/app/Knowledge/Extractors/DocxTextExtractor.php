<?php

namespace Modules\AIAssistant\app\Knowledge\Extractors;

use Modules\AIAssistant\app\Contracts\TextExtractorInterface;
use ZipArchive;

/**
 * DOCX is a zip archive containing word/document.xml — extracted with
 * PHP's built-in ZipArchive, deliberately dependency-free since no DOCX
 * parsing library is available in this project (see architecture doc
 * Part III assessment).
 */
class DocxTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'docx';
    }

    public function extract(string $absolutePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Could not open this DOCX file — it may be corrupt.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('This DOCX file does not contain a readable document body.');
        }

        // Paragraph breaks (<w:p>) become newlines before tags are stripped,
        // so extracted text keeps its paragraph structure for chunking.
        $withBreaks = preg_replace('/<w:p[ >]/', "\n<w:p ", $xml) ?? $xml;
        $text = strip_tags($withBreaks);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1);

        $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
        if ($text === '') {
            throw new \RuntimeException('No readable text found in this DOCX file.');
        }

        return $text;
    }
}
