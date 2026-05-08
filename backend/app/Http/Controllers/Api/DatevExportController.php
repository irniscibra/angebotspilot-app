<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DatevExportController extends Controller
{
    /**
     * DATEV Buchungsstapel Export (CSV)
     * Zeitraum: ?from=2025-01-01&to=2025-12-31
     */
    public function export(Request $request): Response
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $company = $request->user()->company;

        $invoices = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'paid', 'partial_paid', 'overdue'])
            ->whereBetween('created_at', [
                $request->from . ' 00:00:00',
                $request->to   . ' 23:59:59',
            ])
            ->with('customer')
            ->orderBy('invoice_number')
            ->get();

        $csv = $this->buildDatevCsv($invoices, $company, $request->from, $request->to);

        $filename = 'DATEV_Export_' . str_replace('-', '', $request->from)
                  . '_' . str_replace('-', '', $request->to) . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildDatevCsv($invoices, $company, string $from, string $to): string
    {
        $lines = [];

        // ── Zeile 1: DATEV Header ──
        $created    = now()->format('YmdHis') . '000';
        $fromFormatted = str_replace('-', '', $from);
        $toFormatted   = str_replace('-', '', $to);

        $header = [
            '"EXTF"', '700', '21', '"Buchungsstapel"', '9',
            $created, '', '', '"AngebotsPilot Export"', '',
            '1001',   // Beraternummer (Steuerberater trägt eigene ein)
            '1',      // Mandantennummer
            $fromFormatted,
            $toFormatted,
            '', '4', '0', '', '', '"EUR"',
            '', '', '', '', '', '', '', '',
        ];
        $lines[] = implode(';', $header);

        // ── Zeile 2: Spaltenköpfe ──
        $lines[] = implode(';', [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ Umsatz',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Skonto',
            'Buchungstext',
            'Postensperre',
            'Diverse Adressnummer',
            'Geschäftspartnerbank',
            'Sachverhalt',
            'Zinssperre',
            'Beleglink',
        ]);

        // ── Zeilen 3+: Eine Zeile pro Rechnung ──
        foreach ($invoices as $invoice) {
            $customer    = $invoice->customer;
            $amount      = number_format((float) $invoice->total_gross, 2, ',', '');
            $date        = \Carbon\Carbon::parse($invoice->created_at)->format('dm'); // TTMM für DATEV
            $invoiceNum  = $invoice->invoice_number;

            // Erlöskonto: 8400 (SKR03, 19% MwSt) oder 8200 (Kleinunternehmer)
            $erloesKonto = $company->is_small_business ? '8200' : '8400';

            // Debitorenkonto: 10000 + Kunden-ID (einfach, Steuerberater passt an)
            $debitorKonto = '10' . str_pad($customer ? $customer->id : '000', 3, '0', STR_PAD_LEFT);

            $customerName = $customer
                ? trim(($customer->company_name ?: '') . ' ' . $customer->last_name . ' ' . $customer->first_name)
                : 'Unbekannt';
            $buchungstext = mb_substr("Rechnung {$invoiceNum} - " . $customerName, 0, 60);

            $lines[] = implode(';', [
                $amount,          // Umsatz
                'S',              // Soll (Forderung an Kunde)
                'EUR',            // Währung
                '',               // Kurs
                '',               // Basis-Umsatz
                '',               // WKZ Basis
                '"' . $debitorKonto . '"',  // Konto (Debitor)
                '"' . $erloesKonto . '"',   // Gegenkonto (Erlöskonto)
                '',               // BU-Schlüssel
                $date,            // Belegdatum TTMM
                '"' . $invoiceNum . '"',    // Belegfeld 1 (Rechnungsnummer)
                '',               // Belegfeld 2
                '',               // Skonto
                '"' . $buchungstext . '"',  // Buchungstext
                '', '', '', '', '', '',     // restliche Felder leer
            ]);
        }

        // UTF-8 BOM damit Excel es korrekt öffnet
        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }
}