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
        .icon-box { text-align: center; margin-bottom: 24px; }
        .icon-circle { display: inline-block; width: 72px; height: 72px; line-height: 72px; background: #f0fdf4; border-radius: 50%; font-size: 32px; text-align: center; }
        .greeting { font-size: 16px; color: #334155; line-height: 1.7; margin: 0 0 16px; }
        .info-text { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 24px; }
        .security-note { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 16px 18px; font-size: 13.5px; color: #991b1b; line-height: 1.6; margin-bottom: 8px; }
        .security-note strong { display: block; margin-bottom: 4px; font-size: 14px; }
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
        <div class="icon-box">
            <div class="icon-circle">✅</div>
        </div>

        <div class="greeting">
            Hallo {{ $name }},
        </div>

        <div class="info-text">
            das Passwort für Ihr <strong>AngebotsPilot</strong>-Konto wurde soeben erfolgreich geändert.
        </div>

        <div class="security-note">
            <strong>⚠️ Waren Sie das nicht?</strong>
            Falls Sie diese Änderung nicht selbst vorgenommen haben, kontaktieren Sie uns bitte umgehend unter
            <a href="mailto:info@angebotspilot.app" style="color:#991b1b; font-weight:600;">info@angebotspilot.app</a>,
            damit wir Ihr Konto absichern können.
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