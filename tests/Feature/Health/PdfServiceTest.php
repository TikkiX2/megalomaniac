<?php

use App\Services\Health\PdfService;

beforeEach(function () {
    $this->service = new PdfService;

    $pdf = new TCPDF;
    $pdf->SetCreator('Test');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, 'Hello PDF Page One');

    $pdf->AddPage();
    $pdf->Write(0, 'Second Page Content');

    $tmpPath = sys_get_temp_dir().'/health_test_'.uniqid().'.pdf';
    file_put_contents($tmpPath, $pdf->Output('', 'S'));

    $this->pdfPath = $tmpPath;
});

afterEach(function () {
    if (isset($this->pdfPath) && file_exists($this->pdfPath)) {
        @unlink($this->pdfPath);
    }

    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        foreach (glob($this->outputDir.'/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->outputDir);
    }
});

it('extracts text from pdf', function () {
    $text = $this->service->extractText($this->pdfPath);

    expect($text)->toContain('Hello PDF Page One');
    expect($text)->toContain('Second Page Content');
});

it('counts pages correctly', function () {
    $count = $this->service->countPages($this->pdfPath);

    expect($count)->toBe(2);
});

it('rasterizes pdf pages to jpeg when pdftoppm is available', function () {
    if (! shell_exec('which pdftoppm')) {
        $this->markTestSkipped('pdftoppm not available in this environment');
    }

    $this->outputDir = sys_get_temp_dir().'/health_raster_'.uniqid();
    mkdir($this->outputDir);

    $files = $this->service->rasterizePages($this->pdfPath, $this->outputDir, 72);

    expect($files)->toHaveCount(2);
    foreach ($files as $file) {
        expect(file_exists($file))->toBeTrue();
        expect(pathinfo($file, PATHINFO_EXTENSION))->toBe('jpg');
    }
});
