<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to the Operations Feed</title>
    <style>
        body { margin: 0; padding: 0; background: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { background: #0f172a; padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .header { background: #d97706; padding: 32px; text-align: center; }
        .header-icon { width: 56px; height: 56px; background: rgba(255,255,255,0.15); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .header h1 { color: #fff; font-size: 22px; font-weight: 700; margin: 0; }
        .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin: 6px 0 0; }
        .body { padding: 32px; }
        .greeting { color: #f1f5f9; font-size: 16px; margin-bottom: 16px; }
        .message-box { background: #0f172a; border-left: 3px solid #d97706; border-radius: 4px; padding: 16px; margin-bottom: 24px; color: #cbd5e1; font-size: 14px; line-height: 1.6; }
        .section-title { color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px; }
        .feature-list { list-style: none; padding: 0; margin: 0 0 28px; }
        .feature-list li { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #334155; color: #cbd5e1; font-size: 14px; }
        .feature-list li:last-child { border-bottom: none; }
        .badge { width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; margin-top: 2px; }
        .badge-amber { background: rgba(217,119,6,0.2); }
        .badge-blue  { background: rgba(59,130,246,0.2); }
        .badge-green { background: rgba(16,185,129,0.2); }
        .badge-red   { background: rgba(239,68,68,0.2); }
        .cta-wrapper { text-align: center; margin: 28px 0; }
        .cta-btn { display: inline-block; background: #d97706; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 15px; font-weight: 600; letter-spacing: 0.02em; }
        .go-live { background: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 16px; text-align: center; margin-bottom: 24px; }
        .go-live .label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
        .go-live .date { color: #fbbf24; font-size: 20px; font-weight: 700; margin-top: 4px; }
        .footer { padding: 20px 32px; border-top: 1px solid #334155; text-align: center; }
        .footer p { color: #475569; font-size: 12px; margin: 4px 0; }
        .footer a { color: #94a3b8; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        {{-- Header --}}
        <div class="header">
            <div class="header-icon">
                <svg width="28" height="28" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <h1>Operations Feed — You're Invited</h1>
            <p>{{ $team->name }}</p>
        </div>

        {{-- Body --}}
        <div class="body">
            <p class="greeting">Hi {{ $invitee->name }},</p>

            @if ($personalMessage)
                <div class="message-box">{{ $personalMessage }}</div>
            @endif

            <p class="section-title">What is the Operations Feed?</p>
            <ul class="feature-list">
                <li>
                    <div class="badge badge-amber">⚡</div>
                    <div><strong style="color:#f1f5f9">Real-time updates</strong><br>
                        Shift summaries, machine breakdowns, and safety alerts — all in one place, replacing scattered WhatsApp messages.</div>
                </li>
                <li>
                    <div class="badge badge-blue">🗂️</div>
                    <div><strong style="color:#f1f5f9">Structured categories</strong><br>
                        Posts are organised by type — Shift Updates, Breakdowns, Safety, Production, and more.</div>
                </li>
                <li>
                    <div class="badge badge-green">✅</div>
                    <div><strong style="color:#f1f5f9">Approval workflows</strong><br>
                        Important posts require supervisor sign-off before they go live, keeping the feed accurate and reliable.</div>
                </li>
                <li>
                    <div class="badge badge-red">🔔</div>
                    <div><strong style="color:#f1f5f9">Push notifications</strong><br>
                        Enable notifications to receive instant alerts for critical events, even while off-site.</div>
                </li>
            </ul>

            @if ($team->feed_go_live_at)
                <div class="go-live">
                    <div class="label">WhatsApp channels decommission date</div>
                    <div class="date">{{ \Carbon\Carbon::parse($team->feed_go_live_at)->format('l, F j, Y — H:i') }}</div>
                </div>
            @endif

            <div class="cta-wrapper">
                <a href="{{ route('feed') }}" class="cta-btn">Open the Operations Feed →</a>
            </div>

            <p style="color:#64748b;font-size:13px;text-align:center;margin:0;">
                Sign in with your existing {{ $team->name }} credentials.<br>
                If you don't have an account yet, contact your mine administrator.
            </p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>This email was sent on behalf of <strong>{{ $team->name }}</strong></p>
            <p>You're receiving this because you're a team member. <a href="{{ route('home') }}">View platform</a></p>
        </div>
    </div>
</div>
</body>
</html>
