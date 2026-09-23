<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>Payment Approved</title>
</head>
<body style="margin:0;padding:0;background-color:#F7F5F2;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
        Your payment has been approved. Your receipt is now available in your Customer Portal.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background-color:#F7F5F2;">
        <tr>
            <td align="center" style="padding:40px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background-color:#FFFFFF;">
                    <tr>
                        <td style="padding:40px 40px 0 40px;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;font-weight:600;letter-spacing:0.18em;text-transform:uppercase;color:#8F2338;">
                                ROSEWOOD ROYALE
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8E4DF;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 40px 0 40px;">
                            <p style="margin:0 0 12px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#8F2338;">
                                PAYMENT APPROVED
                            </p>
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;line-height:1.6;color:#1C1917;">
                                Hello{{ $userName !== '' ? ' '.$userName : '' }},
                            </p>
                            <p style="margin:16px 0 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#5C5650;">
                                Your payment has been approved. Your receipt is now available in your Customer Portal.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 40px 0 40px;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.5;">
                                <a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="color:#8F2338;text-decoration:none;font-weight:500;">
                                    {{ $actionLabel }}
                                </a>
                            </p>
                        </td>
                    </tr>
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
                    <tr>
                        <td style="padding:36px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8E4DF;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
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
