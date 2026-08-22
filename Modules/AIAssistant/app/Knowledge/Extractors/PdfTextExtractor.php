<?php

namespace Modules\AIAssistant\app\Knowledge\Extractors;

use Modules\AIAssistant\app\Contracts\TextExtractorInterface;

/**
 * Uses spatie/pdf-to-text (wraps the `pdftotext` binary from poppler-utils).
 * NOTE: as of this implementation, that package is listed in composer.lock
 * but not actually vendor-installed in the sandbox this was built in (no
 * outbound network access to run `composer install`), and `pdftotext`
 * itself isn't present either — see architecture doc Part III assessment.
 * The class/binary are checked defensively so a real deployment (with
 * `composer install` + poppler-utils actually available) works without any
 * code change here; until then, PDF uploads fail cleanly with a clear
 * reason rather than silently producing empty text.
 */
class PdfTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'pdf';
    }

    public function extract(string $absolutePath): string
    {
        if (!class_exists(\Spatie\PdfToText\Pdf::class)) {
            throw new \RuntimeException('PDF text extraction is not available on this server — the spatie/pdf-to-text package is not installed.');
        }

        try {
            $text = (new \Spatie\PdfToText\Pdf())->setPdf($absolutePath)->text();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Could not extract text from this PDF: ' . $exception->getMessage());
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('No extractable text found in this PDF (it may be a scanned image without OCR).');
        }

        return $text;
    }
}
