<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /** Generate a valid SVG QR code or throw when the QR library fails. */
    public function generateSvg(string $url, int $size = 200): string
    {
        if (trim($url) === '') {
            throw new RuntimeException('Cannot generate a QR code for an empty URL.');
        }

        try {
            $svg = $this->renderQrSvg($url, $size);
        } catch (\Throwable $e) {
            Log::error('QR code generation failed.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Failed to generate QR code for URL: {$url}", 0, $e);
        }

        if ($svg === '' || !str_contains($svg, '<svg')) {
            Log::error('QR code generation returned an invalid SVG.', ['url' => $url]);

            throw new RuntimeException("QR code generation returned an invalid SVG for URL: {$url}");
        }

        if (!str_contains($svg, 'viewBox')) {
            $svg = preg_replace('/<svg([^>]*)>/', '<svg$1 viewBox="0 0 ' . $size . ' ' . $size . '">', $svg);
        }

        return $svg;
    }

    /** Render the raw SVG using the QR library. */
    protected function renderQrSvg(string $url, int $size): string
    {
        return (string) QrCode::size($size)
            ->margin(4)
            ->color(0, 0, 0)
            ->backgroundColor(255, 255, 255)
            ->generate($url);
    }

    /** Generate a Data URI image source for an img tag. */
    public function generateDataUri(string $url, int $size = 70): string
    {
        $svg = $this->generateSvg($url, $size);

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    /** Build an absolute URL using a publicly reachable application base URL. */
    public function publicUrl(string $path): string
    {
        return rtrim($this->resolvePublicBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /** Resolve the application base URL, preferring configured and request hosts over loopback addresses. */
    protected function resolvePublicBaseUrl(): string
    {
        $configuredBase = trim((string) config('letter_signature.verification_base_url'));

        if ($configuredBase !== '' && !$this->isLoopbackUrl($configuredBase)) {
            return rtrim($configuredBase, '/');
        }

        if (app()->bound('request')) {
            $requestBase = request()->getSchemeAndHttpHost();

            if ($requestBase !== '' && !$this->isLoopbackUrl($requestBase)) {
                return rtrim($requestBase, '/');
            }
        }

        $appUrl = trim((string) config('app.url'));

        if ($appUrl !== '' && !$this->isLoopbackUrl($appUrl)) {
            return rtrim($appUrl, '/');
        }

        $fallback = $configuredBase !== '' ? $configuredBase : ($appUrl !== '' ? $appUrl : 'http://localhost');
        $fallback = rtrim($fallback, '/');

        if (app()->environment('production')) {
            Log::error('QR code verification URL uses a loopback address.', ['base_url' => $fallback]);

            throw new RuntimeException(
                'The letter verification URL resolves to a loopback address. '
                . 'Set LETTER_VERIFICATION_BASE_URL or APP_URL to a publicly accessible URL.'
            );
        }

        Log::warning('QR code verification URL uses a loopback address and will not be scannable from other devices.', [
            'base_url' => $fallback,
        ]);

        return $fallback;
    }

    /** Determine whether a URL points to the local machine. */
    protected function isLoopbackUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_starts_with($host, '127.')
            || str_ends_with($host, '.localhost');
    }
}
