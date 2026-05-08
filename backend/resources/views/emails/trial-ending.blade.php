<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wrapper { padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); padding: 36px 40px; text-align: center; }
        .header-logo { color: #ffffff; font-size: 24px; font-weight: 800; margin: 0; }
        .header-sub { color: #bfdbfe; font-size: 14px; margin: 6px 0 0; }
        .content { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #334155; line-height: 1.7; margin: 0 0 20px; }
        .days-box { text-align: center; background: {{ $daysLeft <= 1 ? '#fef2f2' : ($daysLeft <= 3 ? '#fffbeb' : '#eff6ff') }}; border: 2px solid {{ $daysLeft <= 1 ? '#fca5a5' : ($daysLeft <= 3 ? '#fcd34d' : '#bfdbfe') }}; border-radius: 12px; padding: 24px; margin: 24px 0; }
        .days-number { font-size: 48px; font-weight: 800; color: {{ $daysLeft <= 1 ? '#dc2626' : ($daysLeft <= 3 ? '#d97706' : '#1d4ed8') }}; line-height: 1; }
        .days-label { font-size: 14px; color: #64748b; margin-top: 8px; }
        .info-text { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 24px; }
        .cta-btn { display: block; text-align: center; background: #1d4ed8; color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 10px; font-size: 16px; font-weight: 700; margin: 28px 0; }
        .features { background: #f8fafc; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
        .features-title { font-size: 14px; font-weight: 700; color: #334155; margin: 0 0 12px; }
        .feature-item { font-size: 14px; color: #475569; padding: 4px 0; }
        .footer { padding: 24px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="card">

    <div class="header">
        <div class="header-logo">AngebotsPilot</div>
        <div class="header-sub">KI-Angebotssoftware für Handwerker</div>
    </div>

    <div class="content">
        <div class="greeting">
            Hallo {{ $company->name }},
        </div>

        <div class="days-box">
            <div class="days-number">{{ $daysLeft }}</div>
            <div class="days-label">{{ $daysLeft === 1 ? 'Tag' : 'Tage' }} verbleiben in Ihrem kostenlosen Test</div>
        </div>

        @if($daysLeft <= 1)
        <div class="info-text">
            <strong>Ihr Testzeitraum endet heute.</strong> Um weiterhin auf AngebotsPilot zugreifen zu können und Ihre Daten zu behalten, wählen Sie bitte noch heute ein Abonnement.
        </div>
        @else
        <div class="info-text">
            Ihr kostenloser Testzeitraum läuft in <strong>{{ $daysLeft }} Tagen</strong> ab. Danach wird Ihr Zugang gesperrt, bis Sie ein Abonnement abschließen. Ihre Daten bleiben dabei erhalten.
        </div>
        @endif

        <div class="features">
            <div class="features-title">Was Sie mit AngebotsPilot behalten:</div>
            <div class="feature-item">✅ Alle erstellten Angebote & Rechnungen</div>
            <div class="feature-item">✅ KI-Angebotserstellung in 30 Sekunden</div>
            <div class="feature-item">✅ Datanorm-Import (Würth, Rexel & mehr)</div>
            <div class="feature-item">✅ Digitale Kundenunterschrift</div>
            <div class="feature-item">✅ DATEV-Export für Ihren Steuerberater</div>
            <div class="feature-item">✅ Professionelles Mahnwesen</div>
        </div>

        <a href="https://app.angebotspilot.app/upgrade" class="cta-btn">
            Jetzt Abonnement wählen →
        </a>

        <div class="info-text" style="font-size:13px; color:#94a3b8;">
            Bei Fragen stehen wir Ihnen jederzeit zur Verfügung:<br>
            <a href="mailto:info@nettwebsolutions.de" style="color:#1d4ed8;">info@nettwebsolutions.de</a>
        </div>
    </div>

    <div class="footer">
        AngebotsPilot · NettWebSolutions<br>
        <a href="https://angebotspilot.app" style="color:#94a3b8;">angebotspilot.app</a>
    </div>

</div>
</div>
</div>
</body>
</html>
