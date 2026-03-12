<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #2d3748; line-height: 1.6; padding: 16px; }
        @page { margin: 25mm 25mm 32mm 25mm; }

        .footer { position: fixed; bottom: -22mm; left: 0; right: 0; height: 18mm; border-top: 2px solid {{ $company->primary_color ?? '#1E40AF' }}; padding-top: 8px; font-size: 6.5pt; color: #a0aec0; line-height: 1.5; }
        .footer-content { display: table; width: 100%; }
        .footer-col { display: table-cell; vertical-align: top; width: 33.33%; }
        .footer-col:nth-child(2) { text-align: center; }
        .footer-col:last-child { text-align: right; }
        .footer-label { font-weight: 700; color: #718096; }

        .header { padding: 5px 0 20px 0; margin-bottom: 30px; border-bottom: 3px solid {{ $company->primary_color ?? '#1E40AF' }}; }
        .header-table { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: bottom; width: 55%; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; width: 45%; }
        .company-name { font-size: 20pt; font-weight: 700; color: {{ $company->primary_color ?? '#1E40AF' }}; line-height: 1.2; }
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

        .doc-title { font-size: 14pt; font-weight: 700; color: {{ $company->primary_color ?? '#1E40AF' }}; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #edf2f7; }
        .doc-intro { font-size: 9pt; color: #4a5568; margin-bottom: 35px; line-height: 1.8; }

        .group-header { background: {{ $company->primary_color ?? '#1E40AF' }}0D; border-left: 4px solid {{ $company->primary_color ?? '#1E40AF' }}; padding: 10px 16px; font-size: 9.5pt; font-weight: 700; color: {{ $company->primary_color ?? '#1E40AF' }}; margin-top: 25px; margin-bottom: 0; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items-table thead th { background: #f7fafc; border-bottom: 2px solid #e2e8f0; padding: 10px 12px; font-size: 7pt; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.8px; }
        .items-table thead th:first-child { text-align: left; width: 5%; }
        .items-table thead th:nth-child(2) { text-align: left; width: 43%; }
        .items-table thead th:nth-child(3) { text-align: right; width: 10%; }
        .items-table thead th:nth-child(4) { text-align: center; width: 12%; }
        .items-table thead th:nth-child(5) { text-align: right; width: 15%; }
        .items-table thead th:last-child { text-align: right; width: 15%; }
        .items-table tbody td { padding: 12px 12px; border-bottom: 1px solid #edf2f7; font-size: 8.5pt; vertical-align: top; color: #4a5568; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .item-title { font-weight: 600; color: #1a202c; font-size: 8.5pt; }
        .item-description { font-size: 7.5pt; color: #a0aec0; margin-top: 3px; line-height: 1.4; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .totals-section { margin-top: 30px; display: table; width: 100%; }
        .totals-notes-col { display: table-cell; vertical-align: top; width: 48%; padding-right: 30px; }
        .totals-box-col { display: table-cell; vertical-align: top; width: 52%; }
        .totals-table { width: 100%; border-collapse: collapse; background: #f7fafc; }
        .totals-table td { padding: 8px 16px; font-size: 9pt; }
        .totals-table td:first-child { color: #718096; }
        .totals-table td:last-child { text-align: right; font-weight: 600; color: #2d3748; }
        .totals-table .subtotal-row td { border-bottom: 1px solid #e2e8f0; padding: 7px 16px; font-size: 8.5pt; }
        .totals-table .net-row td { padding-top: 10px; }
        .totals-table .vat-row td { color: #a0aec0; font-size: 8.5pt; }
        .totals-table .vat-row td:last-child { color: #718096; font-weight: 500; }
        .totals-table .total-row td { border-top: 3px solid {{ $company->primary_color ?? '#1E40AF' }}; font-size: 13pt; font-weight: 700; padding-top: 12px; padding-bottom: 12px; }
        .totals-table .total-row td:last-child { color: {{ $company->primary_color ?? '#1E40AF' }}; }
        .totals-table .partial-row td { color: #e53e3e; font-size: 9pt; }
        .totals-table .remaining-row td { border-top: 2px solid #2d3748; font-size: 12pt; font-weight: 700; padding-top: 10px; }

        .bank-section { margin-top: 30px; background: #f7fafc; padding: 14px 18px; }
        .bank-title { font-size: 7.5pt; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .bank-grid { display: table; width: 100%; }
        .bank-col { display: table-cell; vertical-align: top; width: 50%; font-size: 8.5pt; color: #4a5568; line-height: 1.6; }
        .bank-label { color: #a0aec0; font-size: 7pt; font-weight: 700; }

        .terms-section { margin-top: 25px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .terms-label { font-size: 7.5pt; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .terms-text { font-size: 8pt; color: #718096; line-height: 1.7; }

        .small-business-note { margin-top: 15px; font-size: 7.5pt; color: #a0aec0; font-style: italic; }
    </style>
</head>
<body>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-content">
            <div class="footer-col">
                <span class="footer-label">{{ $company->name }}</span><br>
                {{ $company->address_street }}<br>
                {{ $company->address_zip }} {{ $company->address_city }}
            </div>
            <div class="footer-col">
                @if($company->phone)Tel: {{ $company->phone }}<br>@endif
                @if($company->email){{ $company->email }}<br>@endif
                @if($company->website){{ $company->website }}@endif
            </div>
            <div class="footer-col">
                @if($company->tax_id)USt-IdNr: {{ $company->tax_id }}<br>@endif
                @if($company->trade_register){{ $company->trade_register }}@endif
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-table">
            <div class="header-left">
                <div class="company-name">{{ $company->name }}</div>
                <div class="company-subtitle">Sanitär · Heizung · Klimatechnik</div>
            </div>
            <div class="header-right">
                <div class="header-contact">
                    {{ $company->address_street }}<br>
                    {{ $company->address_zip }} {{ $company->address_city }}<br>
                    @if($company->phone)Tel: {{ $company->phone }}<br>@endif
                    @if($company->email){{ $company->email }}@endif
                </div>
            </div>
        </div>
    </div>

    <!-- Absenderzeile -->
    <div class="sender-line">
        {{ $company->name }} · {{ $company->address_street }} · {{ $company->address_zip }} {{ $company->address_city }}
    </div>

    <!-- Adresse + Info -->
    <div class="address-section">
        <div class="recipient-col">
            @if($customer)
                @if($customer->type === 'business')
                    <div class="recipient-name">{{ $customer->company_name }}</div>
                    <div class="recipient-address">
                        @if($customer->contact_person){{ $customer->contact_person }}<br>@endif
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
                <div class="recipient-name">[Kundenname]</div>
                <div class="recipient-address">[Straße]<br>[PLZ Ort]</div>
            @endif
        </div>
        <div class="info-col">
            <table class="info-table">
                <tr><td>Rechnungs-Nr.:</td><td>{{ $invoice->invoice_number }}</td></tr>
                <tr><td>Datum:</td><td>{{ $invoice->created_at->format('d.m.Y') }}</td></tr>
                <tr><td>Fällig bis:</td><td>{{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : '-' }}</td></tr>
                @if($invoice->quote_reference)
                <tr><td>Referenz:</td><td>{{ $invoice->quote_reference }}</td></tr>
                @endif
                @if($invoice->service_date_from || $invoice->service_date_to)
                <tr><td>Leistungszeitraum:</td><td>{{ $invoice->service_date_from ? $invoice->service_date_from->format('d.m.Y') : '' }} – {{ $invoice->service_date_to ? $invoice->service_date_to->format('d.m.Y') : '' }}</td></tr>
                @endif
                <tr><td>Bearbeiter:</td><td>{{ $creator->name }}</td></tr>
            </table>
        </div>
    </div>

    <!-- Titel -->
    <div class="doc-title">{{ $invoice->type_label }}: {{ $invoice->project_title }}</div>

    <!-- Einleitung -->
    <div class="doc-intro">
        @if($invoice->header_text)
            {!! nl2br(e($invoice->header_text)) !!}
        @else
            Sehr {{ $customer && $customer->type === 'business' ? 'geehrte Damen und Herren' : 'geehrte/r ' . ($customer ? ($customer->first_name . ' ' . $customer->last_name) : 'Kunde/Kundin') }},<br><br>
            für die erbrachten Leistungen erlauben wir uns, Ihnen folgenden Betrag in Rechnung zu stellen.
        @endif
    </div>

    <!-- Positionen -->
    @php $posNr = 1; @endphp
    @foreach($groupedItems as $groupName => $items)
        <div class="group-header">{{ $groupName ?: 'Positionen' }}</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Bezeichnung</th>
                    <th class="text-right">Menge</th>
                    <th class="text-center">Einheit</th>
                    <th class="text-right">Einzelpreis</th>
                    <th class="text-right">Gesamtpreis</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td style="color: #a0aec0; font-weight: 600;">{{ str_pad($posNr++, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        <div class="item-title">{{ $item->title }}</div>
                        @if($item->description)
                            <div class="item-description">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: 500;">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2, ',', '.') }}&nbsp;€</td>
                    <td class="text-right" style="font-weight: 600; color: #1a202c;">{{ number_format($item->total_price, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <!-- Summen -->
    <div class="totals-section">
        <div class="totals-notes-col"></div>
        <div class="totals-box-col">
            <table class="totals-table">
                <tr class="subtotal-row">
                    <td>Materialkosten</td>
                    <td>{{ number_format($invoice->subtotal_materials, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                <tr class="subtotal-row">
                    <td>Arbeitsleistung</td>
                    <td>{{ number_format($invoice->subtotal_labor, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @if($invoice->discount_percent > 0)
                <tr class="subtotal-row">
                    <td>Rabatt ({{ number_format($invoice->discount_percent, 1, ',', '.') }}%)</td>
                    <td>-{{ number_format($invoice->discount_amount, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @endif
                <tr class="net-row">
                    <td>Nettobetrag</td>
                    <td>{{ number_format($invoice->subtotal_net, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @if(!$company->is_small_business)
                <tr class="vat-row">
                    <td>MwSt. ({{ number_format($invoice->vat_rate, 0) }}%)</td>
                    <td>{{ number_format($invoice->vat_amount, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Rechnungsbetrag</td>
                    <td>{{ number_format($invoice->total_gross, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @if($invoice->type === 'final' && $invoice->partial_payments_total > 0)
                <tr class="partial-row">
                    <td>Abzgl. geleistete Abschläge</td>
                    <td>-{{ number_format($invoice->partial_payments_total, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                <tr class="remaining-row">
                    <td>Zahlbetrag</td>
                    <td>{{ number_format($invoice->remaining_amount, 2, ',', '.') }}&nbsp;€</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @if($company->is_small_business)
    <div class="small-business-note">
        Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).
    </div>
    @endif

    <!-- Bankverbindung -->
    @if($company->bank_iban)
    <div class="bank-section">
        <div class="bank-title">Bankverbindung</div>
        <div class="bank-grid">
            <div class="bank-col">
                <span class="bank-label">Kontoinhaber</span><br>
                {{ $company->bank_account_holder ?: $company->name }}<br><br>
                <span class="bank-label">Bank</span><br>
                {{ $company->bank_name ?: '-' }}
            </div>
            <div class="bank-col">
                <span class="bank-label">IBAN</span><br>
                {{ $company->bank_iban }}<br><br>
                @if($company->bank_bic)
                <span class="bank-label">BIC</span><br>
                {{ $company->bank_bic }}
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Zahlungsbedingungen -->
    <div class="terms-section">
        <div class="terms-label">Zahlungsbedingungen</div>
        <div class="terms-text">
            @if($invoice->terms_text)
                {!! nl2br(e($invoice->terms_text)) !!}
            @else
                Bitte überweisen Sie den Rechnungsbetrag von {{ number_format($invoice->type === 'final' && $invoice->partial_payments_total > 0 ? $invoice->remaining_amount : $invoice->total_gross, 2, ',', '.') }}&nbsp;€
                bis zum {{ $invoice->due_date ? $invoice->due_date->format('d.m.Y') : 'vereinbarten Termin' }}
                auf das oben genannte Konto unter Angabe der Rechnungsnummer {{ $invoice->invoice_number }}.
            @endif
        </div>
    </div>

    <!-- Schlusstext -->
    <div class="terms-section" style="border-top: none; margin-top: 15px; padding-top: 0;">
        <div class="terms-text">
            @if($invoice->footer_text)
                {!! nl2br(e($invoice->footer_text)) !!}
            @else
                Vielen Dank für Ihren Auftrag. Bei Fragen stehen wir Ihnen gerne zur Verfügung.
            @endif
        </div>
    </div>

</body>
</html>