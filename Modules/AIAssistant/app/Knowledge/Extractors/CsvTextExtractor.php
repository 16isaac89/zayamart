<?php

namespace Modules\AIAssistant\app\Knowledge\Extractors;

use Modules\AIAssistant\app\Contracts\TextExtractorInterface;

class CsvTextExtractor implements TextExtractorInterface
{
    public function supports(string $extension): bool
    {
        return strtolower($extension) === 'csv';
    }

    public function extract(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Could not open this CSV file.');
        }

        $lines = [];
        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $row;
                continue;
            }
            // Render each row as "Column: value" pairs — reads better as
            // retrievable prose than a raw comma-separated line.
            $pairs = [];
            foreach ($row as $i => $value) {
                $pairs[] = trim(($header[$i] ?? "column {$i}") . ': ' . $value);
            }
            $lines[] = implode(', ', $pairs);
        }
        fclose($handle);

        if (empty($lines)) {
            throw new \RuntimeException('This CSV file has no data rows.');
        }

        return implode("\n", $lines);
    }
}
