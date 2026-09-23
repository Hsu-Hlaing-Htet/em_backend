<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>Welcome to {{ $appName }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#F7F5F2;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
        Your Rosewood Royale customer account has been created. Use your login details to access the Customer Portal.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background-color:#F7F5F2;">
        <tr>
            <td align="center" style="padding:40px 20px;">
                {{-- Single clean content column — no nested cards --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background-color:#FFFFFF;">
                    {{-- Brand --}}
                    <tr>
                        <td style="padding:40px 40px 0 40px;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;font-weight:600;letter-spacing:0.18em;text-transform:uppercase;color:#8F2338;">
                                ROSEWOOD ROYALE
                            </p>
                        </td>
                    </tr>

                    {{-- Subtle divider after brand --}}
                    <tr>
                        <td style="padding:24px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8E4DF;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Eyebrow + headline --}}
                    <tr>
                        <td style="padding:28px 40px 0 40px;">
                            <p style="margin:0 0 12px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#8F2338;">
                                ACCOUNT CREATED
                            </p>
                            <h1 style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:28px;line-height:1.25;font-weight:600;color:#1C1917;">
                                Welcome to Rosewood Royale
                            </h1>
                        </td>
                    </tr>

                    {{-- Intro --}}
                    <tr>
                        <td style="padding:20px 40px 0 40px;">
                            <p style="margin:0 0 16px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.55;color:#1C1917;">
                                Hello{{ $userName !== '' ? ' '.$userName : '' }},
                            </p>
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#5C5650;">
                                Your customer account has been created.<br />
                                Use the login details below to access your Customer Portal.
                            </p>
                        </td>
                    </tr>

                    {{-- Credentials (no card border) --}}
                    <tr>
                        <td style="padding:36px 40px 0 40px;">
                            <p style="margin:0 0 8px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#8A837C;">
                                LOGIN EMAIL
                            </p>
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.5;color:#1C1917;word-break:break-all;">
                                <a href="mailto:{{ $loginEmail }}" style="color:#1C1917;text-decoration:none;">{{ $loginEmail }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #EFEBE6;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px 0 40px;">
                            <p style="margin:0 0 8px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#8A837C;">
                                TEMPORARY PASSWORD
                            </p>
                            <p style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;font-size:16px;line-height:1.5;color:#1C1917;word-break:break-all;">
                                {{ $temporaryPassword }}
                            </p>
                        </td>
                    </tr>

                    {{-- Password notice (no warning box) --}}
                    <tr>
                        <td style="padding:36px 40px 0 40px;">
                            <p style="margin:0 0 8px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#8F2338;">
                                PASSWORD NOTICE
                            </p>
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#1C1917;">
                                For your security, you must create a new password after your first login. Do not share this temporary password with anyone.
                            </p>
                        </td>
                    </tr>

                    {{-- Portal text link (no button) --}}
                    <tr>
                        <td style="padding:32px 40px 0 40px;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.5;">
                                <a
                                    href="{{ $actionUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="color:#8F2338;text-decoration:none;font-weight:500;"
                                >Customer Portal Login &rarr;</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Plain URL fallback --}}
                    <tr>
                        <td style="padding:16px 40px 0 40px;">
                            <p style="margin:0 0 6px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.55;color:#8A837C;">
                                If the link above does not work, copy and paste this URL into your browser:
                            </p>
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.55;word-break:break-all;">
                                <a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="color:#8A837C;text-decoration:underline;">
                                    {{ $displayableActionUrl }}
                                </a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer divider --}}
                    <tr>
                        <td style="padding:36px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8E4DF;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 40px 40px 40px;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.5;color:#8A837C;">
                                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
