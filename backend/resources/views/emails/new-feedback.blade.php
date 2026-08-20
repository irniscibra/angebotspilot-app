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
        .info-text { font-size: 15px; color: #475569; line-height: 1.7; margin: 0 0 20px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 6px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; }
        .message-box { background: #f8fafc; border-left: 3px solid #1d4ed8; border-radius: 0 8px 8px 0; padding: 16px 20px; font-size: 15px; color: #334155; line-height: 1.7; margin: 0 0 24px; white-space: pre-wrap; }
        .meta { font-size: 13px; color: #94a3b8; line-height: 1.8; }
        .footer { padding: 24px 40px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="card">

    <div class="header">
        <div class="header-logo">AngebotsPilot</div>
        <div class="header-sub">Neues Feedback eingegangen</div>
    </div>

    <div class="content">
        <div class="badge">{{ ucfirst($feedback->type) }}</div>

        <div class="message-box">{{ $feedback->message }}</div>

        <div class="meta">
            <strong>Firma:</strong> {{ $companyName }}<br>
            <strong>Nutzer:</strong> {{ $userName }}<br>
            @if($feedback->quote_id)
                <strong>Angebot-ID:</strong> {{ $feedback->quote_id }}<br>
            @endif
            @if($feedback->page_context)
                <strong>Seite:</strong> {{ $feedback->page_context }}<br>
            @endif
            <strong>Zeitpunkt:</strong> {{ $feedback->created_at->format('d.m.Y H:i') }} Uhr
        </div>
    </div>

    <div class="footer">
        AngebotsPilot · Internes Feedback-System
    </div>

</div>
</div>
</div>
</body>
</html>