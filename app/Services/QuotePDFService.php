<?php

namespace App\Services;

use App\Models\Quote;
use TCPDF;

class QuotePDFService
{
    public function generate(Quote $quote)
    {
        $pdf = new TCPDF;

        // Document Information
        $pdf->SetCreator('Megalomaniac');
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetTitle('Cotización '.$quote->quote_number);

        // Settings
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // Content
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'COTIZACIÓN', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Nro: '.$quote->quote_number, 0, 1, 'R');
        $pdf->Cell(0, 10, 'Fecha: '.$quote->issue_date->format('d/m/Y'), 0, 1, 'R');

        $pdf->Ln(10);

        // Client Info
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Cliente:', 0, 1);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 5, $quote->client->name."\n".($quote->client->company ?? '')."\n".($quote->client->email ?? ''), 0, 'L');

        $pdf->Ln(10);

        // Items Table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(100, 7, 'Descripción', 1);
        $pdf->Cell(20, 7, 'Horas', 1, 0, 'C');
        $pdf->Cell(30, 7, 'Tarifa/Hora', 1, 0, 'R');
        $pdf->Cell(30, 7, 'Total', 1, 0, 'R');
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 10);
        foreach ($quote->items as $item) {
            $pdf->Cell(100, 7, $item->description, 1);
            $pdf->Cell(20, 7, $item->hours ?? '-', 1, 0, 'C');
            $pdf->Cell(30, 7, number_format($item->hourly_rate, 2), 1, 0, 'R');
            $pdf->Cell(30, 7, number_format($item->subtotal, 2), 1, 0, 'R');
            $pdf->Ln();
        }

        // Totals
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(150, 7, 'Total:', 0, 0, 'R');
        $pdf->Cell(30, 7, number_format($quote->total, 2).' '.$quote->currency->code, 0, 1, 'R');

        // Terms
        if ($quote->terms_and_conditions) {
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 7, 'Términos y Condiciones:', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $quote->terms_and_conditions);
        }

        return $pdf->Output('quote_'.$quote->quote_number.'.pdf', 'S'); // String
    }
}
