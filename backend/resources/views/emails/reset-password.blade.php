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
        .icon-circle { display: inline-block; width: 72px; height: 72px; line-height: 72px; background: #eff6ff; border-radius: 50%; font-size: 32px; text-align: center; }
        .greeting { font-size: 16px; color: #334155; line-height: 1.7; margin: 0 0 16px; }
        .info-text { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 28px; }
        .cta-btn { display: block; text-align: center; background: #1d4ed8; color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 10px; font-size: 16px; font-weight: 700; margin: 0 0 28px; }
        .expire-note { text-align: center; font-size: 13px; color: #94a3b8; margin-bottom: 24px; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .link-fallback { font-size: 12px; color: #94a3b8; word-break: break-all; }
        .link-fallback a { color: #1d4ed8; }
        .security-note { background: #fef3e2; border: 1px solid #fbdca3; border-radius: 10px; padding: 14px 16px; font-size: 13px; color: #92400e; margin-bottom: 28px; }
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
            <div class="icon-circle">🔒</div>
        </div>

        <div class="greeting">
            Hallo {{ $name }},
        </div>

        <div class="info-text">
            wir haben eine Anfrage erhalten, das Passwort für Ihr <strong>AngebotsPilot</strong>-Konto zurückzusetzen.<br><br>
            Klicken Sie auf den Button unten, um ein neues Passwort festzulegen.
        </div>

        <a href="{{ $url }}" class="cta-btn">
            🔑 Neues Passwort festlegen
        </a>

        <div class="expire-note">
            Dieser Link ist <strong>60 Minuten</strong> gültig.
        </div>

        <div class="security-note">
            ⚠️ Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren – Ihr Passwort bleibt unverändert.
        </div>

        <hr class="divider">

        <div class="link-fallback">
            Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>
    </div>

    <div class="footer">
        AngebotsPilot · <br>
        <a href="https://angebotspilot.app" style="color:#94a3b8;">angebotspilot.app</a><br><br>
        Aus Sicherheitsgründen läuft dieser Link nach 60 Minuten ab.
    </div>

</div>
</div>
</div>
</body>
</html>