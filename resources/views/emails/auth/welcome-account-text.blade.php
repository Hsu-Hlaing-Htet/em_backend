{{ $appName }}

Welcome to Rosewood Royale

Hello{{ $userName !== '' ? ' '.$userName : '' }}, your account has been created. Use the login details below to sign in for the first time.

Login email: {{ $loginEmail }}
Temporary password: {{ $temporaryPassword }}

Login to Rosewood Royale:
{{ $actionUrl }}

For your security, you must create a new password after your first login. Do not share this temporary password with anyone.

If the button does not work, copy and paste this URL into your browser:
{{ $displayableActionUrl }}

© 2026 {{ $appName }}. All rights reserved.
