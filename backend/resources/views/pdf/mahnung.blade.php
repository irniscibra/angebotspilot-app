<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #2d3748; line-height: 1.6; padding: 16px; }
        @page { margin: 25mm 25mm 32mm 25mm; }

        .footer { position: fixed; bottom: -22mm; left: 0; right: 0; height: 18mm; border-top: 2px solid {{ $mahnung->company->primary_color ?? '#1E40AF' }}; padding-top: 8px; font-size: 6.5pt; color: #a0aec0; line-height: 1.5; }
        .footer-content { display: table; width: 100%; }
        .footer-col { display: table-cell; vertical-align: top; width: 33.33%; }
        .footer-col:nth-child(2) { text-align: center; }
        .footer-col:last-child { text-align: right; }
        .footer-label { font-weight: 700; color: #718096; }

        .header { padding: 5px 0 20px 0; margin-bottom: 30px; border-bottom: 3px solid {{ $mahnung->company->primary_color ?? '#1E40AF' }}; }
        .header-table { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: bottom; width: 55%; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; width: 45%; }
        .company-name { font-size: 20pt; font-weight: 700; color: {{ $mahnung->company->primary_color ?? '#1E40AF' }}; line-height: 1.2; }
        .company-subtitle { font-size: 8pt; color: #a0aec0; margin-top: 3px; }
        .header-contact { font-size: 7.5pt; color: #718096; line-height: 1.7; }

        .sender-line { font-size: 6.5pt; color: #a0aec0; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 25px; }

        .address-section { display: table; width: 100%; margin-bottom: 40px; }
        .recipient-col { display: table-cell; vertical-align: top; width: 50%; padding-right: 15px; }
        .info-col { display: table-cell; vertical-align: top; width: 50%; }
        .recipient-name { font-weight: 700; font-size: 10.5pt; color: #1a202c; margin-bottom: 4px; }
        .recipient-address { font-size: 9pt; color: #4a5568; line-height: 1.7; }
        .info-table { margin-left: auto; border-collapse: collapse; }
        .info-table td { padding: 4px 0; font-size: 8.5pt; vertical-align: top; }
        .info-table td:first-child { color: #a0aec0; padding-right: 20px; white-space: nowrap; }
        .info-table td:last-child { font-weight: 600; color: #2d3748; text-align: right; }

        .doc-title { font-size: 14pt; font-weight: 700; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #edf2f7; }
        .doc-title-level1 { color: #d97706; }
        .doc-title-level2 { color: #dc2626; }
        .doc-title-level3 { color: #7f1d1d; }

        .warning-box { border-radius: 6px; padding: 14px 18px; margin-bottom: 28px; font-size: 8.5pt; line-height: 1.7; }
        .warning-box-level1 { background: #fffbeb; border-left: 4px solid #d97706; color: #92400e; }
        .warning-box-level2 { background: #fff1f2; border-left: 4px solid #dc2626; color: #991b1b; }
        .warning-box-level3 { background: #fef2f2; border-left: 4px solid #7f1d1d; color: #7f1d1d; }

        .intro-text { font-size: 9pt; color: #4a5568; margin-bottom: 28px; line-height: 1.8; }

        .amount-table { width: 100%; border-collapse: collapse; margin: 28px 0; }
        .amount-table th { background: #f7fafc; border-bottom: 2px solid #e2e8f0; padding: 10px 14px; font-size: 7pt; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; }
        .amount-table th:last-child { text-align: right; }
        .amount-table td { padding: 12px 14px; border-bottom: 1px solid #edf2f7; font-size: 9pt; color: #4a5568; }
        .amount-table td:last-child { text-align: right; font-weight: 600; color: #1a202c; }
        .amount-table .total-row td { border-top: 2px solid #e2e8f0; border-bottom: none; font-weight: 700; font-size: 10pt; }
        .amount-table .total-row td:last-child { color: {{ $mahnung->company->primary_color ?? '#1E40AF' }}; }

        .payment-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px 22px; margin: 28px 0; }
        .payment-box-title { font-size: 9pt; font-weight: 700; color: #15803d; margin-bottom: 10px; }
        .payment-table { width: 100%; border-collapse: collapse; }
        .payment-table td { padding: 5px 0; font-size: 8.5pt; color: #166534; }
        .payment-table td:last-child { text-align: right; font-weight: 600; }

        .closing-text { font-size: 9pt; color: #4a5568; margin-top: 28px; line-height: 1.8; }
        .signature-block { margin-top: 40px; font-size: 9pt; color: #4a5568; }
    </style>
</head>
<body>

{{-- Footer (fixed) --}}
<div class="footer">
    <div class="footer-content">
        <div class="footer-col">
            <span class="footer-label">{{ $mahnung->company->name }}</span><br>
            {{ $mahnung->company->address_street }}<br>
            {{ $mahnung->company->address_zip }} {{ $mahnung->company->address_city }}
        </div>
        <div class="footer-col">
            @if($mahnung->company->phone)<span class="footer-label">Tel:</span> {{ $mahnung->company->phone }}<br>@endif
            @if($mahnung->company->email)<span class="footer-label">E-Mail:</span> {{ $mahnung->company->email }}<br>@endif
            @if($mahnung->company->website){{ $mahnung->company->website }}@endif
        </div>
        <div class="footer-col">
            @if($mahnung->company->bank_iban)<span class="footer-label">IBAN:</span> {{ $mahnung->company->bank_iban }}<br>@endif
            @if($mahnung->company->bank_bic)<span class="footer-label">BIC:</span> {{ $mahnung->company->bank_bic }}<br>@endif
            @if($mahnung->company->tax_id)<span class="footer-label">St-Nr:</span> {{ $mahnung->company->tax_id }}@endif
        </div>
    </div>
</div>

{{-- Header --}}
<div class="header">
    <div class="header-table">
        <div class="header-left">
            <div class="company-name">{{ $mahnung->company->name }}</div>
            @if($mahnung->company->trade)
                <div class="company-subtitle">{{ $mahnung->company->trade }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="header-contact">
                {{ $mahnung->company->address_street }}<br>
                {{ $mahnung->company->address_zip }} {{ $mahnung->company->address_city }}<br>
                @if($mahnung->company->phone)Tel: {{ $mahnung->company->phone }}<br>@endif
                @if($mahnung->company->email){{ $mahnung->company->email }}@endif
            </div>
        </div>
    </div>
</div>

{{-- Absender-Zeile --}}
<div class="sender-line">
    {{ $mahnung->company->name }} · {{ $mahnung->company->address_street }} · {{ $mahnung->company->address_zip }} {{ $mahnung->company->address_city }}
</div>

{{-- Empfänger & Info --}}
<div class="address-section">
    <div class="recipient-col">
        @php $customer = $mahnung->customer; @endphp
        @if($customer)
            <div class="recipient-name">
                @if($customer->type === 'business' && $customer->company_name)
                    {{ $customer->company_name }}<br>
                    <span style="font-weight:400; font-size:9pt;">z. Hd. {{ $customer->first_name }} {{ $customer->last_name }}</span>
                @else
                    {{ $customer->first_name }} {{ $customer->last_name }}
                @endif
            </div>
            <div class="recipient-address">
                @if($customer->address_street){{ $customer->address_street }}<br>@endif
                @if($customer->address_zip || $customer->address_city){{ $customer->address_zip }} {{ $customer->address_city }}@endif
            </div>
        @endif
    </div>
    <div class="info-col">
        <table class="info-table">
            <tr>
                <td>Mahnungsnummer</td>
                <td>{{ $mahnung->mahnung_number }}</td>
            </tr>
            <tr>
                <td>Mahnstufe</td>
                <td>{{ $mahnung->level_label }}</td>
            </tr>
            <tr>
                <td>Rechnungsnummer</td>
                <td>{{ $mahnung->invoice->invoice_number }}</td>
            </tr>
            <tr>
                <td>Rechnungsdatum</td>
                <td>{{ $mahnung->invoice->created_at->format('d.m.Y') }}</td>
            </tr>
            <tr>
                <td>Urspr. Fälligkeit</td>
                <td>{{ $mahnung->original_due_date->format('d.m.Y') }}</td>
            </tr>
            <tr>
                <td>Tage im Verzug</td>
                <td>{{ $mahnung->interest_days }} Tage</td>
            </tr>
            <tr>
                <td>Datum</td>
                <td>{{ now()->format('d.m.Y') }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- Titel --}}
@php
    $titleClass = 'doc-title-level' . $mahnung->level;
    $title = match($mahnung->level) {
        1 => 'Zahlungserinnerung',
        2 => '2. Mahnung',
        3 => '3. und letzte Mahnung',
        default => 'Mahnung'
    };
@endphp
<div class="doc-title {{ $titleClass }}">{{ $title }} – Rechnung {{ $mahnung->invoice->invoice_number }}</div>

{{-- Warnbox --}}
@php $warnClass = 'warning-box-level' . $mahnung->level; @endphp
<div class="warning-box {{ $warnClass }}">
    @if($mahnung->level === 1)
        Laut unserer Unterlagen ist die oben genannte Rechnung noch nicht beglichen. Möglicherweise hat sich diese Erinnerung mit Ihrer Zahlung überschnitten. Falls nicht, bitten wir Sie herzlich um Ausgleich des offenen Betrags bis zum <strong>{{ $mahnung->new_due_date->format('d.m.Y') }}</strong>.
    @elseif($mahnung->level === 2)
        Trotz unserer Zahlungserinnerung haben wir bis heute keinen Zahlungseingang für die oben genannte Rechnung festgestellt. Wir fordern Sie hiermit erneut auf, den ausstehenden Betrag zuzüglich der angefallenen Mahngebühr und Verzugszinsen bis spätestens <strong>{{ $mahnung->new_due_date->format('d.m.Y') }}</strong> zu begleichen.
    @else
        Dies ist unsere letzte Zahlungsaufforderung. Da Sie trotz wiederholter Mahnungen den offenen Betrag nicht beglichen haben, werden wir bei weiterem Ausbleiben der Zahlung bis zum <strong>{{ $mahnung->new_due_date->format('d.m.Y') }}</strong> rechtliche Schritte einleiten und die Forderung einem Inkassounternehmen übergeben.
    @endif
</div>

{{-- Intro --}}
<div class="intro-text">
    @if($customer)
        Sehr geehrte{{ $customer->type === 'business' ? ' Damen und Herren' : (($customer->gender ?? '') === 'female' ? ' Frau' : 'r Herr') }}
        @if($customer->type !== 'business') {{ $customer->last_name }}@endif,
    @else
        Sehr geehrte Damen und Herren,
    @endif
    <br><br>
    @if($mahnung->invoice->project_title)
        bezugnehmend auf das Projekt <strong>„{{ $mahnung->invoice->project_title }}"</strong> erlauben wir uns,
        auf die noch offene Rechnung Nr. <strong>{{ $mahnung->invoice->invoice_number }}</strong> hinzuweisen.
    @else
        wir erlauben uns, auf die noch offene Rechnung Nr. <strong>{{ $mahnung->invoice->invoice_number }}</strong> hinzuweisen.
    @endif
</div>

{{-- Betragstabelle --}}
<table class="amount-table">
    <thead>
        <tr>
            <th>Position</th>
            <th style="text-align:right;">Betrag</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Offener Rechnungsbetrag (Rechnung {{ $mahnung->invoice->invoice_number }})</td>
            <td>{{ number_format($mahnung->original_amount, 2, ',', '.') }} €</td>
        </tr>
        @if($mahnung->mahnung_fee > 0)
        <tr>
            <td>Mahngebühr ({{ $mahnung->level_label }})</td>
            <td>{{ number_format($mahnung->mahnung_fee, 2, ',', '.') }} €</td>
        </tr>
        @endif
        @if($mahnung->interest_amount > 0)
        <tr>
            <td>
                Verzugszinsen ({{ number_format($mahnung->interest_rate, 2, ',', '.') }}% p.a. für {{ $mahnung->interest_days }} Tage)
                <br><span style="font-size:7.5pt; color:#a0aec0;">gem. § 288 Abs. 2 BGB</span>
            </td>
            <td>{{ number_format($mahnung->interest_amount, 2, ',', '.') }} €</td>
        </tr>
        @endif
        <tr class="total-row">
            <td><strong>Gesamtbetrag (fällig bis {{ $mahnung->new_due_date->format('d.m.Y') }})</strong></td>
            <td><strong>{{ number_format($mahnung->total_amount, 2, ',', '.') }} €</strong></td>
        </tr>
    </tbody>
</table>

{{-- Bankverbindung --}}
@if($mahnung->company->bank_iban)
<div class="payment-box">
    <div class="payment-box-title">Bankverbindung für Ihre Überweisung</div>
    <table class="payment-table">
        @if($mahnung->company->bank_account_holder)
        <tr><td>Kontoinhaber</td><td>{{ $mahnung->company->bank_account_holder }}</td></tr>
        @endif
        @if($mahnung->company->bank_name)
        <tr><td>Bank</td><td>{{ $mahnung->company->bank_name }}</td></tr>
        @endif
        <tr><td>IBAN</td><td>{{ $mahnung->company->bank_iban }}</td></tr>
        @if($mahnung->company->bank_bic)
        <tr><td>BIC</td><td>{{ $mahnung->company->bank_bic }}</td></tr>
        @endif
        <tr><td>Verwendungszweck</td><td>{{ $mahnung->mahnung_number }} / {{ $mahnung->invoice->invoice_number }}</td></tr>
        <tr><td>Betrag</td><td>{{ number_format($mahnung->total_amount, 2, ',', '.') }} €</td></tr>
    </table>
</div>
@endif

{{-- Abschlusstext --}}
<div class="closing-text">
    @if($mahnung->level === 1)
        Falls die Zahlung bereits erfolgt ist, betrachten Sie dieses Schreiben bitte als gegenstandslos und wir bedanken uns herzlich.<br><br>
        Bei Rückfragen stehen wir Ihnen gerne zur Verfügung.
    @elseif($mahnung->level === 2)
        Sollte sich Ihre Zahlung mit diesem Schreiben gekreuzt haben, bitten wir Sie, dieses zu ignorieren.<br><br>
        Bei Zahlungsschwierigkeiten oder Rückfragen bitten wir Sie, sich umgehend mit uns in Verbindung zu setzen.
    @else
        Wir bitten Sie dringend, den ausstehenden Betrag bis zum genannten Datum zu überweisen, um weitere Kosten und rechtliche Schritte zu vermeiden.<br><br>
        Bei Fragen wenden Sie sich bitte sofort an uns.
    @endif
    <br><br>
    Mit freundlichen Grüßen
</div>

<div class="signature-block">
    <br>
    {{ $mahnung->company->name }}
</div>

@if($mahnung->notes)
<div style="margin-top:30px; padding:12px 16px; background:#f7fafc; border-radius:6px; font-size:8pt; color:#718096;">
    <strong>Hinweis:</strong> {{ $mahnung->notes }}
</div>
@endif

</body>
</html>
