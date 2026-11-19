<?php

namespace App\Services;

use App\Models\InventoryCampaign;
use App\Models\InventoryItem;
use Illuminate\Http\Response;

class InventoryReportService
{
    public function spreadsheet(InventoryCampaign $campaign, array $filters = []): Response
    {
        $rows = $this->rows($campaign, $filters);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'.
            '<Worksheet ss:Name="Inventaire"><Table>';

        foreach (array_merge([['Référence', 'Désignation', 'Direction', 'Service', 'Localisation théorique', 'Localisation constatée', 'Statut', 'Anomalie physique', 'Anomalie administrative', 'Observations']], $rows) as $row) {
            $xml .= '<Row>'.collect($row)->map(fn ($value) => '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>')->implode('').'</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$campaign->reference.'.xls"',
        ]);
    }

    public function pdf(InventoryCampaign $campaign, array $filters = []): Response
    {
        $rows = $this->rows($campaign, $filters);
        $expected = count(array_filter($rows, fn (array $row) => $row[6] !== 'discovered'));
        $controlled = count(array_filter($rows, fn (array $row) => ! in_array($row[6], ['pending', ''], true)));
        $anomalies = array_filter($rows, fn (array $row) => in_array($row[6], ['present_anomaly', 'absent', 'moved', 'damaged', 'unidentified', 'discovered'], true) || $row[7] !== '' || $row[8] !== '');
        $lines = [
            'REPUBLIQUE DU CONGO - AGENCE DE REGULATION DES TRANSFERTS DE FONDS (ARTF)',
            '[EMPLACEMENT DU LOGO INSTITUTIONNEL]',
            'ARTF - RAPPORT D INVENTAIRE',
            'Campagne : '.$campaign->reference.' - '.$campaign->title,
            'Periode : '.$campaign->starts_on->format('d/m/Y').' au '.$campaign->ends_on->format('d/m/Y'),
            'Date d edition : '.now()->format('d/m/Y H:i'),
            'Biens attendus dans le filtre : '.$expected,
            'Constats saisis : '.$controlled,
            'Biens restants : '.max(0, $expected - $controlled),
            'Ecarts signales : '.count($anomalies),
            'Operations attribuees : '.$campaign->tasks()->count(),
            str_repeat('-', 90),
            'LISTE DES ECARTS',
        ];

        foreach ($anomalies as $row) {
            $lines[] = implode(' | ', array_filter([$row[0], $row[1], $row[6], $row[7], $row[8]], fn ($value) => $value !== ''));
        }

        if ($anomalies === []) {
            $lines[] = 'Aucun ecart signale dans ce filtre.';
        }

        $lines[] = '';
        $lines[] = 'SIGNATURES';
        $lines[] = 'Responsable : ______________________________    Autorite de validation : ______________________________';
        $lines[] = 'Date : _____________________________________    Observations : _______________________________________';

        $pages = array_chunk(array_merge(...array_map(fn ($line) => $this->wrap($line), $lines)), 48);
        $pdf = $this->buildPdf($pages);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$campaign->reference.'.pdf"',
        ]);
    }

    private function rows(InventoryCampaign $campaign, array $filters): array
    {
        return $campaign->items()
            ->with(['asset', 'observedLocation', 'task.scope.direction', 'task.scope.service', 'task.scope.location'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['direction_id'] ?? null, fn ($query, string $id) => $query->where(fn ($q) => $q->where('expected_snapshot->direction_id', $id)->orWhereHas('task.scope', fn ($scope) => $scope->where('direction_id', $id))))
            ->when($filters['service_id'] ?? null, fn ($query, string $id) => $query->where(fn ($q) => $q->where('expected_snapshot->service_id', $id)->orWhereHas('task.scope', fn ($scope) => $scope->where('service_id', $id))))
            ->when($filters['location_id'] ?? null, fn ($query, string $id) => $query->where(fn ($q) => $q->where('expected_snapshot->location_id', $id)->orWhere('observed_location_id', $id)->orWhereHas('task.scope', fn ($scope) => $scope->where('location_id', $id))))
            ->when(filter_var($filters['anomalies'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($query) => $query->where(function ($q): void {
                $q->whereNotNull('physical_anomaly')->orWhereNotNull('administrative_anomaly')->orWhereIn('status', ['present_anomaly', 'absent', 'moved', 'damaged', 'unidentified', 'discovered']);
            }))
            ->orderBy('id')
            ->get()
            ->map(function (InventoryItem $item): array {
                $expected = $item->expected_snapshot ?? [];
                $scope = $item->task?->scope;

                return [
                    $item->asset?->asset_code ?? $item->observed_asset_code ?? '',
                    $item->asset?->designation ?? $item->observed_designation ?? '',
                    $expected['direction'] ?? $scope?->direction?->name ?? '',
                    $expected['service'] ?? $scope?->service?->name ?? '',
                    $expected['location'] ?? '',
                    $item->observedLocation?->name ?? '',
                    $item->status?->value ?? '',
                    $item->physical_anomaly ?? '',
                    $item->administrative_anomaly ?? '',
                    $item->observations ?? '',
                ];
            })
            ->all();
    }

    private function wrap(string $value): array
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return preg_split('/\R/', wordwrap($ascii, 95, "\n", true)) ?: [''];
    }

    private function buildPdf(array $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];

        foreach ($pages as $index => $lines) {
            $pageId = 4 + ($index * 2);
            $contentId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $commands = "BT\n/F1 9 Tf\n40 800 Td\n14 TL\n";

            foreach ($lines as $line) {
                $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $line);
                $commands .= '('.$safe.") Tj\nT*\n";
            }

            $commands .= 'ET';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($commands).">>\nstream\n".$commands."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $document = "%PDF-1.4\n%ARTF\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($document);
            $document .= $id." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($document);
        $document .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        for ($id = 1; $id <= count($objects); $id++) {
            $document .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $document.'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
    }
}
