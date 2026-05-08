<!DOCTYPE html>
<html lang="de" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; width: 100% !important; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wrapper { width: 100%; background-color: #f1f5f9; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }

        .header-level1 { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 36px 40px; text-align: center; }
        .header-level2 { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 36px 40px; text-align: center; }
        .header-level3 { background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%); padding: 36px 40px; text-align: center; }

        .header-company { color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; }
        .header-subtitle { color: rgba(255,255,255,0.8); font-size: 13px; margin: 6px 0 0; }
        .header-badge { display: inline-block; background: rgba(255,255,255,0.2); color: #fff; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 14px; }

        .content { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #334155; line-height: 1.7; margin: 0 0 20px; }

        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 26px; margin: 0 0 26px; }
        .info-row { width: 100%; border-collapse: collapse; }
        .info-row td { padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .info-row tr:last-child td { border-bottom: none; }
        .info-label { color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .info-value { font-size: 14px; color: #0f172a; font-weight: 600; text-align: right; }

        .amount-highlight { background: #fff7ed; border: 2px solid #fed7aa; border-radius: 10px; padding: 18px 22px; margin: 0 0 26px; text-align: center; }
        .amount-highlight-level2 { background: #fff1f2; border-color: #fecaca; }
        .amount-highlight-level3 { background: #fef2f2; border-color: #fca5a5; }
        .amount-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 6px; }
        .amount-value { font-size: 28px; font-weight: 800; color: #dc2626; }

        .due-date-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 20px; margin: 0 0 26px; text-align: center; }
        .due-date-label { font-size: 12px; color: #15803d; font-weight: 600; }
        .due-date-value { font-size: 18px; font-weight: 800; color: #15803d; margin-top: 4px; }

        .bank-box { background: #f8fafc; border-radius: 10px; padding: 18px 22px; margin: 0 0 26px; }
        .bank-title { font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 12px; }
        .bank-row { width: 100%; border-collapse: collapse; }
        .bank-row td { padding: 6px 0; font-size: 13px; color: #475569; border-bottom: 1px solid #f1f5f9; }
        .bank-row tr:last-child td { border-bottom: none; }
        .bank-row td:last-child { text-align: right; font-weight: 600; color: #0f172a; }

        .warning-text-level1 { background: #fffbeb; border-left: 4px solid #d97706; padding: 14px 18px; border-radius: 0 8px 8px 0; font-size: 14px; color: #92400e; line-height: 1.7; margin: 0 0 26px; }
        .warning-text-level2 { background: #fff1f2; border-left: 4px solid #dc2626; padding: 14px 18px; border-radius: 0 8px 8px 0; font-size: 14px; color: #991b1b; line-height: 1.7; margin: 0 0 26px; }
        .warning-text-level3 { background: #fef2f2; border-left: 4px solid #7f1d1d; padding: 14px 18px; border-radius: 0 8px 8px 0; font-size: 14px; color: #7f1d1d; line-height: 1.7; margin: 0 0 26px; }

        .closing { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 26px; }
        .sender { font-size: 15px; color: #334155; font-weight: 600; }

        .footer { padding: 24px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="card">

    {{-- Header --}}
    @php
        $headerClass = 'header-level' . $mahnung->level;
        $badge = match($mahnung->level) {
            1 => '⚠️ Zahlungserinnerung',
            2 => '🔴 2. Mahnung',
            3 => '🚨 3. und letzte Mahnung',
            default => 'Mahnung'
        };
    @endphp
    <div class="{{ $headerClass }}">
        <div class="header-company">{{ $mahnung->company->name }}</div>
        <div class="header-subtitle">{{ $mahnung->company->trade ?? 'Handwerksbetrieb' }}</div>
        <div class="header-badge">{{ $badge }}</div>
    </div>

    {{-- Content --}}
    <div class="content">
        @php $customer = $mahnung->customer; @endphp
        <div class="greeting">
            @if($customer)
                Sehr geehrte{{ $customer->type === 'business' ? ' Damen und Herren' : 'r Herr / Frau ' . $customer->last_name }},
            @else
                Sehr geehrte Damen und Herren,
            @endif
        </div>

        {{-- Hinweisbox je nach Level --}}
        @if($mahnung->level === 1)
        <div class="warning-text-level1">
            laut unseren Unterlagen ist folgende Rechnung noch offen. Möglicherweise hat sich diese Erinnerung mit Ihrer Zahlung überschnitten – bitte ignorieren Sie dieses Schreiben in diesem Fall.
        </div>
        @elseif($mahnung->level === 2)
        <div class="warning-text-level2">
            trotz unserer Zahlungserinnerung haben wir bis heute keinen Zahlungseingang festgestellt. Wir fordern Sie hiermit erneut zur Begleichung des Betrags auf.
        </div>
        @else
        <div class="warning-text-level3">
            dies ist unsere letzte Zahlungsaufforderung. Bei weiterem Ausbleiben der Zahlung werden wir rechtliche Schritte einleiten und die Forderung einem Inkassounternehmen übergeben.
        </div>
        @endif

        {{-- Rechnungsdetails --}}
        <div class="info-box">
            <table class="info-row">
                <tr>
                    <td class="info-label">Mahnungsnummer</td>
                    <td class="info-value">{{ $mahnung->mahnung_number }}</td>
                </tr>
                <tr>
                    <td class="info-label">Rechnungsnummer</td>
                    <td class="info-value">{{ $mahnung->invoice->invoice_number }}</td>
                </tr>
                @if($mahnung->invoice->project_title)
                <tr>
                    <td class="info-label">Projekt</td>
                    <td class="info-value">{{ $mahnung->invoice->project_title }}</td>
                </tr>
                @endif
                <tr>
                    <td class="info-label">Ursprüngliche Fälligkeit</td>
                    <td class="info-value">{{ $mahnung->original_due_date->format('d.m.Y') }}</td>
                </tr>
                <tr>
                    <td class="info-label">Tage im Verzug</td>
                    <td class="info-value">{{ $mahnung->interest_days }} Tage</td>
                </tr>
            </table>
        </div>

        {{-- Offener Betrag --}}
        @php $amountClass = $mahnung->level >= 2 ? 'amount-highlight-level' . $mahnung->level : ''; @endphp
        <div class="amount-highlight {{ $amountClass }}">
            <div class="amount-label">Offener Gesamtbetrag</div>
            <div class="amount-value">{{ number_format($mahnung->total_amount, 2, ',', '.') }} €</div>
            @if($mahnung->mahnung_fee > 0 || $mahnung->interest_amount > 0)
            <div style="font-size:12px; color:#6b7280; margin-top:6px;">
                inkl. Mahngebühr ({{ number_format($mahnung->mahnung_fee, 2, ',', '.') }} €)
                @if($mahnung->interest_amount > 0)
                + Zinsen ({{ number_format($mahnung->interest_amount, 2, ',', '.') }} €)
                @endif
            </div>
            @endif
        </div>

        {{-- Neue Fälligkeit --}}
        <div class="due-date-box">
            <div class="due-date-label">Neue Zahlungsfrist</div>
            <div class="due-date-value">{{ $mahnung->new_due_date->format('d.m.Y') }}</div>
        </div>

        {{-- Bankverbindung --}}
        @if($mahnung->company->bank_iban)
        <div class="bank-box">
            <div class="bank-title">Bitte überweisen Sie auf folgendes Konto:</div>
            <table class="bank-row">
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
                <tr><td>Verwendungszweck</td><td>{{ $mahnung->mahnung_number }}</td></tr>
                <tr><td>Betrag</td><td style="color:#dc2626;">{{ number_format($mahnung->total_amount, 2, ',', '.') }} €</td></tr>
            </table>
        </div>
        @endif

        <div class="closing">
            Das Mahndokument finden Sie im Anhang dieser E-Mail.<br><br>
            @if($mahnung->level === 1)
                Bei Fragen stehen wir Ihnen gerne zur Verfügung.
            @elseif($mahnung->level === 2)
                Bei Zahlungsschwierigkeiten bitten wir Sie, sich umgehend mit uns in Verbindung zu setzen.
            @else
                Zur Vermeidung weiterer Kosten bitten wir um sofortige Kontaktaufnahme.
            @endif
            <br><br>
            Mit freundlichen Grüßen,
        </div>

        <div class="sender">{{ $mahnung->company->name }}</div>
        @if($mahnung->company->phone)
        <div style="font-size:13px; color:#94a3b8; margin-top:6px;">📞 {{ $mahnung->company->phone }}</div>
        @endif
        @if($mahnung->company->email)
        <div style="font-size:13px; color:#94a3b8;">✉️ {{ $mahnung->company->email }}</div>
        @endif
    </div>

    <div class="footer">
        Diese E-Mail wurde über AngebotsPilot versendet.<br>
        {{ $mahnung->company->name }} · {{ $mahnung->company->address_street }} · {{ $mahnung->company->address_zip }} {{ $mahnung->company->address_city }}
    </div>

</div>
</div>
</div>
</body>
</html>
