<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\View\View;

class AdministrativeDocumentService
{
    public function preview(string $title, string $reference, array $sections, string $pdfUrl): View
    {
        return view('admin.documents.show', [
            'title' => $title,
            'reference' => $reference,
            'issuedAt' => now(),
            'sections' => $sections,
            'pdfUrl' => $pdfUrl,
        ]);
    }

    public function pdf(string $title, string $reference, array $sections, string $filename): Response
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.pdf';
        $lines = [
            'REPUBLIQUE DU CONGO - AGENCE DE REGULATION DES TRANSFERTS DE FONDS (ARTF)',
            '[EMPLACEMENT DU LOGO INSTITUTIONNEL]',
            $title,
            'Reference : '.$reference,
            'Date d edition : '.now()->format('d/m/Y H:i'),
            str_repeat('-', 92),
        ];

        foreach ($sections as $heading => $entries) {
            $lines[] = strtoupper($heading);
            foreach ($entries as $label => $value) {
                $value = is_array($value) ? implode(' ; ', array_filter($value, fn ($part) => $part !== null && $part !== '')) : $value;
                $lines[] = $label.' : '.($value === null || $value === '' ? 'Non renseigne' : (string) $value);
            }
            $lines[] = '';
        }

        $lines[] = 'SIGNATURES';
        $lines[] = 'Responsable : __________________________    Beneficiaire / controleur : __________________________';
        $lines[] = 'Date : _________________________________    Observations : ____________________________________';

        return response($this->buildPdf($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function spreadsheet(string $title, array $headings, array $rows, string $filename): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'.
            '<Worksheet ss:Name="Patrimoine"><Table>';
        foreach (array_merge([[$title], $headings], $rows) as $row) {
            $xml .= '<Row>'.collect($row)->map(fn ($value) => '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>')->implode('').'</Row>';
        }
        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function buildPdf(array $lines): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $line) ?: (string) $line;
            foreach (preg_split('/\R/', wordwrap($ascii, 92, "\n", true)) ?: [''] as $piece) {
                $wrapped[] = $piece;
            }
        }
        $header = array_slice($wrapped, 0, 6);
        $pages = array_chunk(array_slice($wrapped, 6), 42) ?: [[]];
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];

        foreach ($pages as $index => $bodyLines) {
            $pageLines = [...$header, ...$bodyLines];
            $pageId = 4 + ($index * 2);
            $contentId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $commands = "BT\n/F1 9 Tf\n40 790 Td\n14 TL\n";
            foreach ($pageLines as $line) {
                $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $commands .= '('.$safe.") Tj\nT*\n";
            }
            $commands .= 'ET';
            $commands .= "\nBT\n/F1 8 Tf\n470 24 Td\n(Page ".($index + 1).' / '.count($pages).") Tj\nET";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($commands).">>\nstream\n".$commands."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%ARTF\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
    }
}
