<!DOCTYPE html>
<html lang="de" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Angebot {{ $quote->quote_number }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; background-color: #f1f5f9; }

        /* Main Styles */
        .wrapper { width: 100%; background-color: #f1f5f9; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }

        /* Header */
        .header { background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 50%, #2563eb 100%); padding: 40px; text-align: center; }
        .header-company { color: #ffffff; font-size: 24px; font-weight: 800; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; letter-spacing: -0.02em; }
        .header-subtitle { color: #bfdbfe; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 8px 0 0; }
        .header-badge { display: inline-block; background: rgba(255,255,255,0.15); color: #fff; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* Body */
        .content { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #334155; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.7; margin: 0 0 20px; }
        .message { font-size: 15px; color: #475569; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.7; margin: 0 0 28px; }

        /* Quote Details Box */
        .quote-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px 28px; margin: 0 0 28px; }
        .quote-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .quote-row:last-child { border-bottom: none; }
        .quote-row-table { width: 100%; border-collapse: collapse; }
        .quote-row-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .quote-row-table tr:last-child td { border-bottom: none; }
        .quote-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .quote-value { font-size: 15px; color: #0f172a; font-weight: 600; text-align: right; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* Total */
        .total-box { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #bfdbfe; border-radius: 12px; padding: 20px 28px; margin: 0 0 28px; text-align: center; }
        .total-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .total-amount { font-size: 36px; color: #1d4ed8; font-weight: 800; margin: 8px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; letter-spacing: -0.02em; }
        .total-note { font-size: 12px; color: #94a3b8; margin: 4px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* PDF Info */
        .pdf-notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 16px 20px; margin: 0 0 28px; }
        .pdf-notice-text { font-size: 14px; color: #166534; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; }
        .pdf-notice-icon { font-size: 18px; margin-right: 8px; }

        /* Signature */
        .signature { font-size: 15px; color: #334155; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.7; margin: 0; }
        .signature-name { font-weight: 700; color: #0f172a; font-size: 16px; }

        /* Footer */
        .footer { background: #f8fafc; padding: 24px 40px; border-top: 1px solid #e2e8f0; }
        .footer-text { font-size: 12px; color: #94a3b8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; margin: 0; text-align: center; }
        .footer-brand { font-size: 11px; color: #cbd5e1; margin: 12px 0 0; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* Divider */
        .divider { height: 1px; background: #e2e8f0; margin: 24px 0; }

        /* Responsive */
        @media only screen and (max-width: 640px) {
            .wrapper { padding: 16px 0 !important; }
            .content { padding: 24px 20px !important; }
            .header { padding: 28px 20px !important; }
            .footer { padding: 20px !important; }
            .quote-box { padding: 16px 18px !important; }
            .total-amount { font-size: 28px !important; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="card">
                <!-- Header -->
                <div class="header">
                    <p class="header-company">{{ $quote->company->name ?? 'Ihr Handwerksbetrieb' }}</p>
                    <p class="header-subtitle">Ihr professionelles Angebot</p>
                    <div class="header-badge">📋 {{ $quote->quote_number }}</div>
                </div>

                <!-- Content -->
                <div class="content">
                    <!-- Greeting -->
                    <p class="greeting">Sehr geehrte/r <strong>{{ $recipientName }}</strong>,</p>

                    <!-- Custom Message or Default -->
                    @if($customMessage)
                        <p class="message">{{ $customMessage }}</p>
                    @else
                        <p class="message">vielen Dank für Ihr Interesse und Ihr Vertrauen. Gerne unterbreiten wir Ihnen nachfolgendes Angebot für Ihr Projekt.</p>
                    @endif

                    <!-- Quote Details -->
                    <div class="quote-box">
                        <table class="quote-row-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td><span class="quote-label">Projekt</span></td>
                                <td class="quote-value">{{ $quote->project_title }}</td>
                            </tr>
                            <tr>
                                <td><span class="quote-label">Angebotsnr.</span></td>
                                <td class="quote-value">{{ $quote->quote_number }}</td>
                            </tr>
                            <tr>
                                <td><span class="quote-label">Positionen</span></td>
                                <td class="quote-value">{{ $quote->items->count() }} Leistungen</td>
                            </tr>
                            @if($quote->valid_until)
                            <tr>
                                <td><span class="quote-label">Gültig bis</span></td>
                                <td class="quote-value">{{ \Carbon\Carbon::parse($quote->valid_until)->format('d.m.Y') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><span class="quote-label">Nettobetrag</span></td>
                                <td class="quote-value">{{ number_format($quote->subtotal_net, 2, ',', '.') }} €</td>
                            </tr>
                            <tr>
                                <td><span class="quote-label">MwSt. ({{ $quote->vat_rate }}%)</span></td>
                                <td class="quote-value">{{ number_format($quote->vat_amount, 2, ',', '.') }} €</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Total -->
                    <div class="total-box">
                        <p class="total-label">Gesamtbetrag inkl. MwSt.</p>
                        <p class="total-amount">{{ number_format($quote->total_gross, 2, ',', '.') }} €</p>
                        <p class="total-note">Alle Preise in Euro</p>
                    </div>

                    <!-- PDF Notice -->
                    <div class="pdf-notice">
                        <p class="pdf-notice-text">📎 Das vollständige Angebot mit allen Positionen finden Sie als PDF im Anhang dieser E-Mail.</p>
                    </div>

                    <div class="divider"></div>

                    <!-- Signature -->
                    <p class="message">Bei Rückfragen stehen wir Ihnen selbstverständlich jederzeit gerne zur Verfügung. Antworten Sie einfach auf diese E-Mail.</p>

                    <p class="signature">
                        Mit freundlichen Grüßen<br>
                        <span class="signature-name">{{ $senderName }}</span><br>
                        <span style="color: #64748b; font-size: 14px;">{{ $quote->company->name ?? '' }}</span>
                        @if($quote->company->phone)
                            <br><span style="color: #94a3b8; font-size: 13px;">Tel: {{ $quote->company->phone }}</span>
                        @endif
                        @if($quote->company->email)
                            <br><span style="color: #94a3b8; font-size: 13px;">{{ $quote->company->email }}</span>
                        @endif
                    </p>
                </div>

                <!-- Footer -->
                <div class="footer">
                    <p class="footer-text">
                        Diese E-Mail wurde automatisch erstellt.<br>
                        Antworten Sie direkt auf diese E-Mail um {{ $senderName }} zu kontaktieren.
                    </p>
                    <p class="footer-brand">Erstellt mit AngebotsPilot</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>