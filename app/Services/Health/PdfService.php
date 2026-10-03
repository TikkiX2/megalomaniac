<?php

namespace App\Services\Health;

use Smalot\PdfParser\Parser;

class PdfService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser;
    }

    public function extractText(string $pdfPath): string
    {
        if (! file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        $pdf = $this->parser->parseFile($pdfPath);

        return $pdf->getText();
    }

    public function countPages(string $pdfPath): int
    {
        if (! file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        $pdf = $this->parser->parseFile($pdfPath);

        return count($pdf->getPages());
    }

    public function rasterizePages(string $pdfPath, string $outputDir, int $dpi = 150): array
    {
        if (! file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
                throw new \RuntimeException("Unable to create output directory: {$outputDir}");
            }
        }

        $basename = pathinfo($pdfPath, PATHINFO_FILENAME);
        $prefix = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$basename;

        $cmd = sprintf(
            'pdftoppm -jpeg -r %d %s %s',
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        );

        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('pdftoppm failed with code '.$returnCode.': '.implode("\n", $output));
        }

        $pattern = $prefix.'-*.jpg';
        $files = glob($pattern);

        if ($files === false) {
            $files = [];
        }

        sort($files);

        return $files;
    }
}
