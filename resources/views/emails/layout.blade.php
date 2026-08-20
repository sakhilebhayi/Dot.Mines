<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background-color:#211a14;-webkit-text-size-adjust:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background-color:#211a14;padding:40px 16px;">
        <tr>
            <td align="center">

                <!-- Email card -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="max-width:600px;background-color:#2c2319;border-radius:14px;border:1px solid #3a2f22;">

                    <!-- Amber accent bar -->
                    <tr>
                        <td style="background-color:#d99e2b;height:4px;border-radius:14px 14px 0 0;font-size:1px;line-height:1px;">&nbsp;</td>
                    </tr>

                    <!-- Header: logo + app name -->
                    <tr>
                        <td style="background-color:#2c2319;padding:24px 32px;border-bottom:1px solid #3a2f22;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <!-- Logo mark: the real Dot.Mines mark served from the
                                             application host (asset() respects APP_URL, so the
                                             image URL is correct per environment). The gold
                                             letter block remains as the alt/fallback identity
                                             for clients that block remote images. -->
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="display:inline-table;vertical-align:middle;">
                                            <tr>
                                                <td style="background-color:#d99e2b;width:38px;height:38px;border-radius:9px;text-align:center;vertical-align:middle;font-size:20px;font-weight:900;color:#2c2319;letter-spacing:-0.04em;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
                                                    <img src="{{ asset('images/mark.png') }}" width="38" height="38" alt="D" style="display:block;border-radius:9px;width:38px;height:38px;" />
                                                </td>
                                                <td style="padding-left:11px;vertical-align:middle;">
                                                    <span style="color:#f4efe4;font-size:20px;font-weight:700;letter-spacing:-0.02em;">{{ config('app.name') }}</span>
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
                        <td style="background-color:#3a2f22;padding:24px 32px;border-bottom:1px solid #4a3d2c;">
                            @yield('banner')
                        </td>
                    </tr>
                    @endif

                    <!-- Body -->
                    <tr>
                        <td style="background-color:#2c2319;padding:32px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#211a14;border-top:1px solid #3a2f22;padding:20px 32px;text-align:center;border-radius:0 0 14px 14px;">
                            <p style="margin:0 0 5px;font-size:12px;color:#a89a7c;">
                                Questions? Reach us at
                                <a href="mailto:{{ config('mail.addresses.info') }}" style="color:#c9b896;text-decoration:none;">{{ config('mail.addresses.info') }}</a>
                            </p>
                            @hasSection('footer_note')
                            <p style="margin:4px 0 5px;font-size:12px;color:#8a7c60;">
                                @yield('footer_note')
                            </p>
                            @endif
                            @hasSection('unsubscribe_link')
                            <p style="margin:4px 0 5px;font-size:11px;color:#8a7c60;">
                                @yield('unsubscribe_link')
                            </p>
                            @endif
                            <p style="margin:0;font-size:11px;color:#4a3d2c;">
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
