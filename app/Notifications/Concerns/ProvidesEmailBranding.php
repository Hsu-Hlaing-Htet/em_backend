<?php

namespace App\Notifications\Concerns;

trait ProvidesEmailBranding
{
    public function brandLogoSrc(): ?string
    {
        $publicUrl = $this->resolvePublicLogoUrl();

        if ($publicUrl !== null) {
            return $publicUrl;
        }

        return $this->inlineLogoDataUri();
    }

    public function brandLogoUrl(): ?string
    {
        return $this->resolvePublicLogoUrl();
    }

    private function resolvePublicLogoUrl(): ?string
    {
        $candidates = [
            config('mail.logo_url'),
            rtrim((string) config('app.url'), '/').'/images/logo-dark.jpg',
            rtrim((string) config('app.frontend_url'), '/').'/images/logo-dark.jpg',
        ];

        foreach ($candidates as $candidate) {
            $url = $this->absolutePublicImageUrl($candidate);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function inlineLogoDataUri(): ?string
    {
        foreach ([
            public_path('images/logo-dark.jpg'),
            resource_path('images/logo-dark.jpg'),
            resource_path('documents/logo-dark.jpg'),
        ] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $mime = mime_content_type($path) ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return null;
    }

    private function absolutePublicImageUrl(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            return null;
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return null;
        }

        return $url;
    }
}
