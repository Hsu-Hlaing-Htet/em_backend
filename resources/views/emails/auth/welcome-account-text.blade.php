ROSEWOOD ROYALE

ACCOUNT CREATED

Welcome to Rosewood Royale

Hello{{ $userName !== '' ? ' '.$userName : '' }},

Your customer account has been created.
Use the login details below to access your Customer Portal.

LOGIN EMAIL
{{ $loginEmail }}

TEMPORARY PASSWORD
{{ $temporaryPassword }}

PASSWORD NOTICE
For your security, you must create a new password after your first login. Do not share this temporary password with anyone.

Customer Portal Login:
{{ $actionUrl }}

If the link above does not work, copy and paste this URL into your browser:
{{ $displayableActionUrl }}

© {{ date('Y') }} {{ $appName }}. All rights reserved.
