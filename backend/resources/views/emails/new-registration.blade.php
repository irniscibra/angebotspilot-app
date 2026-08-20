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
        .header { background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); padding: 36px 40px; text-align: center; }
        .header-logo { color: #ffffff; font-size: 24px; font-weight: 800; margin: 0; }
        .header-sub { color: #dcfce7; font-size: 14px; margin: 6px 0 0; }
        .content { padding: 36px 40px; }
        .meta { font-size: 15px; color: #334155; line-height: 2; }
        .footer { padding: 24px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="card">

    <div class="header">
        <div class="header-logo">AngebotsPilot</div>
        <div class="header-sub">🎉 Neue Registrierung</div>
    </div>

    <div class="content">
        <div class="meta">
            <strong>Firma:</strong> {{ $company->name }}<br>
            <strong>Name:</strong> {{ $user->name }}<br>
            <strong>E-Mail:</strong> {{ $user->email }}<br>
            <strong>Zeitpunkt:</strong> {{ $user->created_at->format('d.m.Y H:i') }} Uhr
        </div>
    </div>

    <div class="footer">
        AngebotsPilot · Internes Benachrichtigungssystem
    </div>

</div>
</div>
</div>
</body>
</html>