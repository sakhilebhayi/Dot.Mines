<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background-color:#070d1a;-webkit-text-size-adjust:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background-color:#070d1a;padding:40px 16px;">
        <tr>
            <td align="center">

                <!-- Email card -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="max-width:600px;background-color:#0f172a;border-radius:14px;border:1px solid #1e293b;">

                    <!-- Amber accent bar -->
                    <tr>
                        <td style="background-color:#f59e0b;height:4px;border-radius:14px 14px 0 0;font-size:1px;line-height:1px;">&nbsp;</td>
                    </tr>

                    <!-- Header: logo + app name -->
                    <tr>
                        <td style="background-color:#0f172a;padding:24px 32px;border-bottom:1px solid #1e293b;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <!-- Logo mark -->
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="display:inline-table;vertical-align:middle;">
                                            <tr>
                                                <td style="background-color:#f59e0b;width:38px;height:38px;border-radius:9px;text-align:center;vertical-align:middle;font-size:20px;font-weight:900;color:#0f172a;letter-spacing:-0.04em;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
                                                    M
                                                </td>
                                                <td style="padding-left:11px;vertical-align:middle;">
                                                    <span style="color:#f1f5f9;font-size:20px;font-weight:700;letter-spacing:-0.02em;">{{ config('app.name') }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    @hasSection('header_badge')
                                    <td style="text-align:right;vertical-align:middle;">
                                        @yield('header_badge')
                                    </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @hasSection('banner')
                    <!-- Subject banner -->
                    <tr>
                        <td style="background-color:#1e293b;padding:24px 32px;border-bottom:1px solid #334155;">
                            @yield('banner')
                        </td>
                    </tr>
                    @endif

                    <!-- Body -->
                    <tr>
                        <td style="background-color:#0f172a;padding:32px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#070d1a;border-top:1px solid #1e293b;padding:20px 32px;text-align:center;border-radius:0 0 14px 14px;">
                            <p style="margin:0 0 5px;font-size:12px;color:#64748b;">
                                Questions? Reach us at
                                <a href="mailto:{{ config('mail.addresses.info', 'info@mines.infodot.co.za') }}" style="color:#94a3b8;text-decoration:none;">{{ config('mail.addresses.info', 'info@mines.infodot.co.za') }}</a>
                            </p>
                            @hasSection('footer_note')
                            <p style="margin:4px 0 5px;font-size:12px;color:#475569;">
                                @yield('footer_note')
                            </p>
                            @endif
                            <p style="margin:0;font-size:11px;color:#334155;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- /Email card -->

            </td>
        </tr>
    </table>
</body>
</html>
