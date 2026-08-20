<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>Reset Your Password - {{ $appName }}</title>
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
<body style="margin:0;padding:0;background-color:#F6EFE8;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
        Reset the password for your Rosewood Royale account.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background-color:#F6EFE8;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background-color:#FFFFFF;border:1px solid #E8DCD4;border-radius:16px;">
                    <tr>
                        <td align="center" style="padding:36px 40px 12px 40px;">
                            @if (! empty($logoSrc))
                                <img
                                    src="{{ $logoSrc }}"
                                    alt="Rosewood Royale"
                                    width="72"
                                    height="72"
                                    style="display:block;width:72px;height:72px;border:0;outline:none;text-decoration:none;border-radius:16px;"
                                />
                            @endif

                            <p style="margin:16px 0 0 0;font-family:Georgia,'Times New Roman',Times,serif;font-size:13px;letter-spacing:2.4px;text-transform:uppercase;color:#80152F;">
                                {{ $appName }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 40px 0 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8DCD4;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 40px 8px 40px;">
                            <h1 style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:28px;line-height:1.3;font-weight:normal;color:#1A1412;">
                                Reset Your Password
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 40px 0 40px;">
                            <p style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:16px;line-height:1.7;color:#3F3532;">
                                We received a request to reset the password for your Rosewood Royale account.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:32px 40px 8px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="70%" style="width:70%;max-width:390px;">
                                <tr>
                                    <td align="center" bgcolor="#80152F" style="background-color:#80152F;border:2px solid #80152F;border-radius:6px;">
                                        <a
                                            href="{{ $actionUrl }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            style="display:block;background-color:#80152F;color:#ffffff;font-family:Georgia,'Times New Roman',Times,serif;font-size:16px;font-weight:400;line-height:24px;padding:8px 16px;text-align:center;text-decoration:none;border:none;outline:none;border-radius:6px;"
                                        >
                                            <span style="color:#ffffff;text-decoration:none;font-weight:400;">Reset Password</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px 0 40px;">
                            <p style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:15px;line-height:1.7;color:#3F3532;">
                                This password reset link will expire in {{ $expireMinutes }} minutes.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 40px 0 40px;">
                            <p style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:15px;line-height:1.7;color:#6B5E58;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 40px 8px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="border-top:1px solid #E8DCD4;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 40px 8px 40px;">
                            <p style="margin:0 0 8px 0;font-family:Georgia,'Times New Roman',Times,serif;font-size:13px;line-height:1.6;color:#6B5E58;">
                                If the button does not work, copy and paste this URL into your browser:
                            </p>
                            <p style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:13px;line-height:1.6;word-break:break-all;">
                                <a href="{{ $actionUrl }}" target="_blank" rel="noopener noreferrer" style="color:#80152F;text-decoration:underline;">
                                    {{ $displayableActionUrl }}
                                </a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:28px 40px 36px 40px;">
                            <p style="margin:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:12px;line-height:1.6;color:#8A7C76;">
                                &copy; 2026 {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
