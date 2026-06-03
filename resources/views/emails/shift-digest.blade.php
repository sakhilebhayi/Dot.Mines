<div style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color: #111827; max-width: 600px; margin: 0 auto;">

    {{-- Header --}}
    <div style="background: #0f172a; padding: 24px 32px; border-radius: 8px 8px 0 0;">
        <h1 style="color: #f1f5f9; font-size: 20px; margin: 0 0 4px 0;">{{ $teamName }}</h1>
        <p style="color: #94a3b8; font-size: 14px; margin: 0;">Shift Digest &mdash; {{ $shiftLabel }}</p>
    </div>

    {{-- Body --}}
    <div style="background: #1e293b; padding: 28px 32px; border-radius: 0 0 8px 8px;">

        {{-- Stats By Category --}}
        <h2 style="color: #e2e8f0; font-size: 16px; margin: 0 0 12px 0; border-bottom: 1px solid #334155; padding-bottom: 8px;">
            Post Summary
        </h2>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
            <thead>
                <tr>
                    <th style="text-align: left; color: #94a3b8; font-size: 12px; font-weight: 600; padding: 6px 0; text-transform: uppercase; letter-spacing: 0.05em;">Category</th>
                    <th style="text-align: right; color: #94a3b8; font-size: 12px; font-weight: 600; padding: 6px 0; text-transform: uppercase; letter-spacing: 0.05em;">Posts</th>
                    <th style="text-align: right; color: #94a3b8; font-size: 12px; font-weight: 600; padding: 6px 0; text-transform: uppercase; letter-spacing: 0.05em;">Acknowledged</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stats['by_category'] as $cat => $data)
                    <tr>
                        <td style="color: #e2e8f0; font-size: 14px; padding: 6px 0; border-bottom: 1px solid #1e293b;">
                            {{ ucfirst(str_replace('_', ' ', $cat)) }}
                        </td>
                        <td style="color: #e2e8f0; font-size: 14px; padding: 6px 0; border-bottom: 1px solid #334155; text-align: right;">
                            {{ $data['count'] ?? 0 }}
                        </td>
                        <td style="color: #94a3b8; font-size: 14px; padding: 6px 0; border-bottom: 1px solid #334155; text-align: right;">
                            {{ $data['acknowledged'] ?? 0 }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Unacknowledged Critical --}}
        @if (!empty($stats['unacknowledged_critical']))
            <div style="background: #450a0a; border: 1px solid #7f1d1d; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px;">
                <p style="color: #fca5a5; font-size: 14px; font-weight: 600; margin: 0 0 4px 0;">
                    ⚠ {{ $stats['unacknowledged_critical'] }} critical post(s) unacknowledged
                </p>
                <p style="color: #f87171; font-size: 13px; margin: 0;">These require immediate attention.</p>
            </div>
        @endif

        {{-- Breakdown Summary --}}
        @if (!empty($stats['breakdown_count']))
            <div style="background: #1c1917; border: 1px solid #44403c; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px;">
                <p style="color: #d6d3d1; font-size: 14px; margin: 0;">
                    <strong style="color: #fbbf24;">{{ $stats['breakdown_count'] }}</strong> breakdown(s) reported this shift.
                    @if (!empty($stats['resolved_breakdowns']))
                        <strong style="color: #4ade80;">{{ $stats['resolved_breakdowns'] }}</strong> resolved.
                    @endif
                </p>
            </div>
        @endif

        {{-- Top Posts --}}
        @if (!empty($topPosts))
            <h2 style="color: #e2e8f0; font-size: 16px; margin: 0 0 12px 0; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                Most Engaged Posts
            </h2>
            @foreach ($topPosts as $post)
                <div style="background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 12px 16px; margin-bottom: 10px;">
                    <p style="color: #94a3b8; font-size: 12px; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.04em;">
                        {{ ucfirst(str_replace('_', ' ', $post['category'])) }}
                        &middot; {{ $post['likes'] ?? 0 }} likes
                        &middot; {{ $post['comments'] ?? 0 }} comments
                    </p>
                    <p style="color: #e2e8f0; font-size: 14px; margin: 0;">
                        {{ \Illuminate\Support\Str::limit($post['body'], 180) }}
                    </p>
                    <p style="color: #64748b; font-size: 12px; margin: 6px 0 0 0;">— {{ $post['author'] ?? 'Unknown' }}</p>
                </div>
            @endforeach
        @endif

        {{-- Pending Approvals (supervisors only) --}}
        @if (!empty($pendingApprovals))
            <h2 style="color: #e2e8f0; font-size: 16px; margin: 24px 0 12px 0; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                Pending Approvals ({{ count($pendingApprovals) }})
            </h2>
            @foreach ($pendingApprovals as $post)
                <div style="background: #1a1a2e; border: 1px solid #3730a3; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px;">
                    <p style="color: #a5b4fc; font-size: 12px; margin: 0 0 2px 0;">
                        {{ ucfirst(str_replace('_', ' ', $post['category'])) }}
                        &middot; {{ $post['author'] ?? 'Unknown' }}
                    </p>
                    <p style="color: #e2e8f0; font-size: 13px; margin: 0;">{{ \Illuminate\Support\Str::limit($post['body'], 150) }}</p>
                </div>
            @endforeach
        @endif

        {{-- CTA --}}
        <div style="text-align: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid #334155;">
            <a href="{{ route('feed') }}"
               style="display: inline-block; background: #2563eb; color: white; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600;">
                View Feed
            </a>
        </div>

        <p style="color: #475569; font-size: 12px; text-align: center; margin-top: 20px;">
            — {{ config('app.name') }}<br>
            <a href="{{ route('settings') }}" style="color: #475569;">Manage notification preferences</a>
        </p>
    </div>
</div>
