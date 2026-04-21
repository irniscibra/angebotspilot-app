<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 28mm 20mm 35mm 20mm;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1a1a2e;
            line-height: 1.55;
            background: #ffffff;
        }

        /* ===== WASSERZEICHEN LOGO ===== */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.03;
            z-index: -1;
            font-size: 120pt;
            font-weight: 900;
            color: {{ $company->primary_color ?? '#1E40AF' }};
            letter-spacing: -3px;
            white-space: nowrap;
        }

        /* ===== FOOTER ===== */
        .footer {
            position: fixed;
            bottom: -28mm;
            left: 0;
            right: 0;
            height: 26mm;
            padding-top: 6px;
        }
        .footer-line {
            height: 2px;
            background: linear-gradient(90deg, {{ $company->primary_color ?? '#1E40AF' }}, {{ $company->primary_color ?? '#1E40AF' }}44);
            margin-bottom: 8px;
        }
        .footer-grid {
            display: table;
            width: 100%;
        }
        .footer-cell {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
            font-size: 6pt;
            color: #94a3b8;
            line-height: 1.7;
        }
        .footer-cell:nth-child(2) { text-align: center; }
        .footer-cell:last-child { text-align: right; }
        .footer-label {
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 5.5pt;
            margin-bottom: 2px;
        }
        .footer-page {
            position: fixed;
            bottom: -28mm;
            right: 0;
            font-size: 6pt;
            color: #cbd5e1;
            text-align: right;
            padding-top: 4px;
        }

        /* ===== HEADER ===== */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 0.5px solid #e2e8f0;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 55%;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 45%;
        }
        .logo-img {
            max-height: 52px;
            max-width: 200px;
            margin-bottom: 6px;
        }
        .company-name {
            font-size: 18pt;
            font-weight: 700;
            color: {{ $company->primary_color ?? '#1E40AF' }};
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .company-tagline {
            font-size: 7pt;
            color: #94a3b8;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .header-contact-block {
            font-size: 7pt;
            color: #64748b;
            line-height: 1.8;
            text-align: right;
        }
        .header-contact-block strong {
            color: #334155;
            font-weight: 600;
        }

        /* ===== SENDER ZEILE ===== */
        .sender-line {
            font-size: 6pt;
            color: #94a3b8;
            padding-bottom: 10px;
            margin-bottom: 18px;
            border-bottom: 0.5px solid #f1f5f9;
            letter-spacing: 0.2px;
        }
        .sender-underline {
            display: inline;
            border-bottom: 0.5px solid #94a3b8;
            padding-bottom: 1px;
        }

        /* ===== ADRESS + INFO BLOCK ===== */
        .address-section {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }
        .recipient-col {
            display: table-cell;
            width: 52%;
            vertical-align: top;
            padding-right: 20px;
        }
        .info-col {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }
        .recipient-name {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .recipient-address {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.7;
        }

        /* Info Box rechts */
        .info-box {
            border: 0.5px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .info-box-header {
            background: {{ $company->primary_color ?? '#1E40AF' }};
            padding: 6px 12px;
            font-size: 6.5pt;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .info-box-body {
            padding: 0;
        }
        .info-row {
            display: table;
            width: 100%;
            border-bottom: 0.5px solid #f1f5f9;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-key {
            display: table-cell;
            padding: 5px 12px;
            font-size: 7pt;
            color: #94a3b8;
            width: 40%;
            white-space: nowrap;
        }
        .info-val {
            display: table-cell;
            padding: 5px 12px 5px 0;
            font-size: 7.5pt;
            font-weight: 600;
            color: #1e293b;
            text-align: right;
        }

        /* ===== ANGEBOTS TITEL ===== */
        .quote-title-section {
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 0.5px solid #e2e8f0;
        }
        .quote-label {
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: {{ $company->primary_color ?? '#1E40AF' }};
            margin-bottom: 4px;
        }
        .quote-title {
            font-size: 14pt;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        /* ===== INTRO TEXT ===== */
        .quote-intro {
            font-size: 8.5pt;
            color: #475569;
            margin-bottom: 22px;
            line-height: 1.7;
        }

        /* ===== POSITIONS GRUPPEN ===== */
        .group-header {
            display: table;
            width: 100%;
            margin-top: 18px;
            margin-bottom: 0;
        }
        .group-header-bar {
            display: table-cell;
            width: 4px;
            background: {{ $company->primary_color ?? '#1E40AF' }};
            border-radius: 2px 0 0 0;
        }
        .group-header-content {
            display: table-cell;
            background: {{ $company->primary_color ?? '#1E40AF' }}0F;
            padding: 7px 14px;
            font-size: 8.5pt;
            font-weight: 700;
            color: {{ $company->primary_color ?? '#1E40AF' }};
            letter-spacing: 0.2px;
        }

        /* ===== TABELLE ===== */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .items-table thead th {
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding: 7px 10px;
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .items-table thead th.col-pos { text-align: left; width: 6%; }
        .items-table thead th.col-bez { text-align: left; width: 42%; }
        .items-table thead th.col-menge { text-align: right; width: 10%; }
        .items-table thead th.col-einheit { text-align: center; width: 10%; }
        .items-table thead th.col-ep { text-align: right; width: 15%; }
        .items-table thead th.col-gp { text-align: right; width: 17%; }

        .items-table tbody td {
            padding: 9px 10px;
            vertical-align: top;
            font-size: 8pt;
            color: #475569;
            border-bottom: 0.5px solid #f1f5f9;
        }
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        .items-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .pos-nr {
            font-size: 7pt;
            font-weight: 600;
            color: #94a3b8;
        }
        .item-title {
            font-size: 8.5pt;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .item-desc {
            font-size: 6.5pt;
            color: #94a3b8;
            line-height: 1.45;
        }
        .type-badge {
            display: inline-block;
            font-size: 5pt;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 5px;
            vertical-align: middle;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-material {
            background: {{ $company->primary_color ?? '#1E40AF' }}1A;
            color: {{ $company->primary_color ?? '#1E40AF' }};
        }
        .badge-labor {
            background: #fef9c3;
            color: #854d0e;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .amount {
            font-weight: 600;
            color: #0f172a;
            font-size: 8.5pt;
        }

        /* ===== SUMMEN SECTION ===== */
        .totals-section {
            margin-top: 24px;
            display: table;
            width: 100%;
        }
        .totals-left {
            display: table-cell;
            width: 46%;
            vertical-align: top;
            padding-right: 24px;
        }
        .totals-right {
            display: table-cell;
            width: 54%;
            vertical-align: top;
        }

        /* Hinweis Box */
        .notes-box {
            border-left: 3px solid #fbbf24;
            background: #fffbeb;
            padding: 10px 12px;
            border-radius: 0 4px 4px 0;
        }
        .notes-title {
            font-size: 6pt;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .notes-text {
            font-size: 7pt;
            color: #78716c;
            line-height: 1.6;
        }

        /* Summen Tabelle */
        .totals-box {
            border: 0.5px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .totals-inner {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-inner td {
            padding: 7px 14px;
            font-size: 8pt;
        }
        .totals-inner .t-label { color: #64748b; }
        .totals-inner .t-val { text-align: right; font-weight: 600; color: #334155; }
        .totals-inner .sub-row td { border-bottom: 0.5px solid #f1f5f9; font-size: 7.5pt; }
        .totals-inner .sub-row .t-label { color: #94a3b8; }
        .totals-inner .sub-row .t-val { color: #64748b; font-weight: 500; }
        .totals-inner .net-row td { padding-top: 8px; font-weight: 600; }
        .totals-inner .vat-row td { font-size: 7.5pt; }
        .totals-inner .vat-row .t-label { color: #94a3b8; }
        .totals-inner .vat-row .t-val { color: #94a3b8; font-weight: 400; }
        .totals-inner .total-row td {
            background: {{ $company->primary_color ?? '#1E40AF' }};
            color: #ffffff;
            font-size: 11pt;
            font-weight: 700;
            padding: 10px 14px;
        }
        .totals-inner .total-row .t-val { color: #ffffff; }

        @if($quote->discount_percent > 0)
        .totals-inner .discount-row td { color: #16a34a; font-size: 7.5pt; }
        .totals-inner .discount-row .t-val { font-weight: 600; }
        @endif

        /* ===== ZAHLUNGSBEDINGUNGEN ===== */
        .terms-section {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 0.5px solid #e2e8f0;
            display: table;
            width: 100%;
        }
        .terms-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .terms-col:last-child {
            padding-right: 0;
        }
        .section-label {
            font-size: 6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .terms-text {
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.7;
        }

        /* ===== SCHLUSSTEXT ===== */
        .closing-section {
            margin-top: 20px;
        }
        .closing-text {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.7;
        }

        /* ===== UNTERSCHRIFTEN ===== */
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .sig-block {
            display: table-cell;
            width: 40%;
            vertical-align: bottom;
        }
        .sig-spacer {
            display: table-cell;
            width: 20%;
        }
        .sig-line {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
        }
        .sig-label {
            font-size: 7pt;
            color: #94a3b8;
            line-height: 1.6;
        }
        .sig-name {
            font-size: 7.5pt;
            font-weight: 600;
            color: #334155;
        }

        /* ===== PFLICHTANGABEN BANNER ===== */
        .legal-banner {
            margin-top: 20px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 0.5px solid #e2e8f0;
            border-radius: 4px;
            font-size: 6pt;
            color: #94a3b8;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <!-- WASSERZEICHEN -->
    <div class="watermark">{{ strtoupper(substr($company->name, 0, 1)) }}</div>

    <!-- FOOTER (fixed) -->
    <div class="footer">
        <div class="footer-line"></div>
        <div class="footer-grid">
            <div class="footer-cell">
                <div class="footer-label">{{ $company->name }}</div>
                {{ $company->address_street ?? '' }}<br>
                {{ $company->address_zip ?? '' }} {{ $company->address_city ?? '' }}
            </div>
            <div class="footer-cell">
                @if($company->phone)Tel: {{ $company->phone }}<br>@endif
                @if($company->email){{ $company->email }}<br>@endif
                @if($company->website){{ $company->website }}@endif
            </div>
            <div class="footer-cell">
                @if($company->tax_id)<strong>USt-IdNr.:</strong> {{ $company->tax_id }}<br>@endif
                @if($company->trade_register){{ $company->trade_register }}<br>@endif
                Seite <span class="pagenum"></span>
            </div>
        </div>
    </div>

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            @if($company->logo_path)
                <img src="{{ public_path('storage/' . $company->logo_path) }}" class="logo-img" alt="{{ $company->name }}">
            @else
                <div class="company-name">{{ $company->name }}</div>
                <div class="company-tagline">Sanitär · Heizung · Klimatechnik</div>
            @endif
        </div>
        <div class="header-right">
            <div class="header-contact-block">
                @if($company->logo_path)
                    <strong>{{ $company->name }}</strong><br>
                @endif
                {{ $company->address_street ?? '' }}<br>
                {{ $company->address_zip ?? '' }} {{ $company->address_city ?? '' }}<br>
                @if($company->phone)Tel: {{ $company->phone }}<br>@endif
                @if($company->email){{ $company->email }}<br>@endif
                @if($company->website){{ $company->website }}@endif
            </div>
        </div>
    </div>

    <!-- SENDER ZEILE -->
    <div class="sender-line">
        <span class="sender-underline">{{ $company->name }} · {{ $company->address_street ?? '' }} · {{ $company->address_zip ?? '' }} {{ $company->address_city ?? '' }}</span>
    </div>

    <!-- ADRESSE + INFO BOX -->
    <div class="address-section">
        <div class="recipient-col">
            @if($customer)
                @if($customer->type === 'business')
                    <div class="recipient-name">{{ $customer->company_name }}</div>
                    <div class="recipient-address">
                        @if($customer->contact_person)z. Hd. {{ $customer->contact_person }}<br>@endif
                        @if($customer->address_street){{ $customer->address_street }}<br>@endif
                        @if($customer->address_zip){{ $customer->address_zip }} {{ $customer->address_city }}@endif
                    </div>
                @else
                    <div class="recipient-name">{{ $customer->first_name }} {{ $customer->last_name }}</div>
                    <div class="recipient-address">
                        @if($customer->address_street){{ $customer->address_street }}<br>@endif
                        @if($customer->address_zip){{ $customer->address_zip }} {{ $customer->address_city }}@endif
                    </div>
                @endif
            @else
                <div class="recipient-name">[Kein Kunde zugewiesen]</div>
            @endif
        </div>
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-header">Angebotsinformationen</div>
                <div class="info-box-body">
                    <div class="info-row">
                        <div class="info-key">Angebots-Nr.</div>
                        <div class="info-val">{{ $quote->quote_number }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-key">Datum</div>
                        <div class="info-val">{{ $quote->created_at->format('d.m.Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-key">Gültig bis</div>
                        <div class="info-val">{{ $quote->valid_until ? $quote->valid_until->format('d.m.Y') : '-' }}</div>
                    </div>
                    @if($quote->project_address)
                    <div class="info-row">
                        <div class="info-key">Bauvorhaben</div>
                        <div class="info-val">{{ $quote->project_address }}</div>
                    </div>
                    @endif
                    <div class="info-row">
                        <div class="info-key">Bearbeiter</div>
                        <div class="info-val">{{ $creator->name }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ANGEBOTS TITEL -->
    <div class="quote-title-section">
        <div class="quote-label">Angebot</div>
        <div class="quote-title">{{ $quote->project_title }}</div>
    </div>

    <!-- INTRO TEXT -->
    <div class="quote-intro">
        @if($quote->header_text)
            {!! nl2br(e($quote->header_text)) !!}
        @else
            Sehr {{ $customer && $customer->type === 'business' ? 'geehrte Damen und Herren' : 'geehrte/r ' . ($customer ? ($customer->first_name . ' ' . $customer->last_name) : 'Kundin, sehr geehrter Kunde') }},<br><br>
            vielen Dank für Ihre Anfrage. Anbei erhalten Sie unser Angebot für die genannten Leistungen.
            Alle Preise sind Nettopreise in Euro zzgl. der gesetzlichen Mehrwertsteuer von {{ number_format($quote->vat_rate ?? 19, 0) }}%.
        @endif
    </div>

    <!-- POSITIONEN -->
    @php $posNr = 1; @endphp

    @foreach($groupedItems as $groupName => $items)
        <!-- Gruppen Header -->
        <div class="group-header">
            <div class="group-header-bar"></div>
            <div class="group-header-content">{{ $groupName ?: 'Positionen' }}</div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-pos">Pos.</th>
                    <th class="col-bez">Bezeichnung</th>
                    <th class="col-menge text-right">Menge</th>
                    <th class="col-einheit text-center">Einheit</th>
                    <th class="col-ep text-right">Einzelpr.</th>
                    <th class="col-gp text-right">Gesamtpr.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td><span class="pos-nr">{{ str_pad($posNr++, 2, '0', STR_PAD_LEFT) }}</span></td>
                    <td>
                        <div class="item-title">
                            {{ $item->title }}
                            <span class="type-badge {{ $item->type === 'material' ? 'badge-material' : 'badge-labor' }}">
                                {{ $item->type === 'material' ? 'Material' : 'Arbeit' }}
                            </span>
                        </div>
                        @if($item->description)
                            <div class="item-desc">{{ Str::limit($item->description, 180) }}</div>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight:500;">
                        {{ number_format($item->quantity, 2, ',', '.') }}
                    </td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2, ',', '.') }}&nbsp;€</td>
                    <td class="text-right"><span class="amount">{{ number_format($item->total_price, 2, ',', '.') }}&nbsp;€</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <!-- SUMMEN -->
    <div class="totals-section">
        <div class="totals-left">
            @if($quote->ai_response && isset($quote->ai_response['notes']))
                <div class="notes-box">
                    <div class="notes-title">Hinweise zur Ausführung</div>
                    <div class="notes-text">{{ $quote->ai_response['notes'] }}</div>
                </div>
            @endif
        </div>
        <div class="totals-right">
            <div class="totals-box">
                <table class="totals-inner">
                    <tr class="sub-row">
                        <td class="t-label">Materialkosten</td>
                        <td class="t-val">{{ number_format($quote->subtotal_materials, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                    <tr class="sub-row">
                        <td class="t-label">Arbeitsleistung</td>
                        <td class="t-val">{{ number_format($quote->subtotal_labor, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                    @if($quote->discount_percent > 0)
                    <tr class="sub-row discount-row">
                        <td class="t-label">Rabatt ({{ number_format($quote->discount_percent, 1, ',', '.') }}%)</td>
                        <td class="t-val">−&nbsp;{{ number_format($quote->discount_amount, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                    @endif
                    <tr class="net-row">
                        <td class="t-label">Nettobetrag</td>
                        <td class="t-val">{{ number_format($quote->subtotal_net, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                    <tr class="vat-row">
                        <td class="t-label">zzgl. MwSt. {{ number_format($quote->vat_rate ?? 19, 0) }}%</td>
                        <td class="t-val">{{ number_format($quote->vat_amount, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                    <tr class="total-row">
                        <td class="t-label">Gesamtbetrag</td>
                        <td class="t-val">{{ number_format($quote->total_gross, 2, ',', '.') }}&nbsp;€</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ZAHLUNGSBEDINGUNGEN + HINWEISE -->
    <div class="terms-section">
        <div class="terms-col">
            <div class="section-label">Zahlungsbedingungen</div>
            <div class="terms-text">
                @if($quote->terms_text)
                    {!! nl2br(e($quote->terms_text)) !!}
                @else
                    Zahlbar innerhalb von 14 Tagen nach Rechnungsstellung ohne Abzug.
                    Bei Aufträgen über 5.000&nbsp;€ netto wird eine Anzahlung von 40&nbsp;% bei Auftragserteilung fällig.
                    Dieses Angebot ist {{ $company->quote_validity_days ?? 30 }} Tage gültig.
                @endif
            </div>
        </div>
        <div class="terms-col">
            <div class="section-label">Ausführungshinweise</div>
            <div class="terms-text">
                Alle Arbeiten werden gemäß den geltenden DIN-, VDE- und DVGW-Normen sowie den anerkannten Regeln der Technik ausgeführt.
                Preise verstehen sich netto zzgl. gesetzlicher MwSt.
            </div>
        </div>
    </div>

    <!-- SCHLUSSTEXT -->
    <div class="closing-section">
        <div class="closing-text">
            @if($quote->footer_text)
                {!! nl2br(e($quote->footer_text)) !!}
            @else
                Wir würden uns freuen, den Auftrag ausführen zu dürfen, und stehen für Rückfragen jederzeit gerne zur Verfügung.<br>
                Mit freundlichen Grüßen
            @endif
        </div>
    </div>

    <!-- UNTERSCHRIFTEN -->
    <div class="signature-section">
        <div class="sig-block">
            <br><br><br>
            <div class="sig-line">
                <div class="sig-name">{{ $creator->name }}</div>
                <div class="sig-label">{{ $company->name }}<br>Ort, Datum &amp; Unterschrift</div>
            </div>
        </div>
        <div class="sig-spacer"></div>
        <div class="sig-block">
            <br><br><br>
            <div class="sig-line">
                <div class="sig-name">&nbsp;</div>
                <div class="sig-label">Auftraggeber/in<br>Ort, Datum &amp; Unterschrift zur Auftragserteilung</div>
            </div>
        </div>
    </div>

    <!-- PFLICHTANGABEN -->
    <div class="legal-banner">
        <strong>{{ $company->name }}</strong>
        @if($company->address_street) · {{ $company->address_street }}, {{ $company->address_zip }} {{ $company->address_city }}@endif
        @if($company->phone) · Tel: {{ $company->phone }}@endif
        @if($company->email) · {{ $company->email }}@endif
        @if($company->tax_id) · USt-IdNr.: {{ $company->tax_id }}@endif
        @if($company->trade_register) · {{ $company->trade_register }}@endif
    </div>

</body>
</html>