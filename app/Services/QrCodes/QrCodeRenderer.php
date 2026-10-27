<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WebPWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QrCodeRenderer
{
    /** @var array<string, string> */
    private const CONTENT_TYPES = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function renderDraftPreview(QrCode $qrCode): Response
    {
        $response = $this->render(
            $qrCode,
            'svg',
            download: false,
            data: route('qr-codes.public', 'preview-only'),
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function render(QrCode $qrCode, string $format, bool $download = true, ?string $data = null): Response
    {
        abort_unless(array_key_exists($format, self::CONTENT_TYPES), 404);
        $logoPath = $this->logoPath($qrCode);
        $writer = match ($format) {
            'svg' => new SvgWriter,
            'webp' => new WebPWriter,
            default => new PngWriter,
        };
        $logoSize = min(128, intdiv($qrCode->size, 4));
        $result = (new Builder(
            writer: $writer,
            data: $data ?? route('qr-codes.public', $qrCode->code),
            errorCorrectionLevel: ErrorCorrectionLevel::tryFrom($qrCode->error_correction) ?? ErrorCorrectionLevel::Low,
            size: $qrCode->size,
            margin: $qrCode->margin,
            foregroundColor: $this->color($qrCode->foreground_color),
            backgroundColor: $this->color($qrCode->background_color),
            logoPath: $logoPath ?? '',
            logoResizeToWidth: $logoPath === null ? null : $logoSize,
            logoResizeToHeight: $logoPath === null ? null : $logoSize,
        ))->build();

        $content = $format === 'jpg' ? $this->jpeg($result->getString()) : $result->getString();
        $headers = ['Content-Type' => self::CONTENT_TYPES[$format]];
        if ($download) {
            $headers['Content-Disposition'] = "attachment; filename=\"qr-{$qrCode->code}.{$format}\"";
        }

        return response($content, 200, $headers);
    }

    private function logoPath(QrCode $qrCode): ?string
    {
        if ($qrCode->logo_path === null) {
            return null;
        }

        $disk = Storage::disk('local');
        abort_unless(
            str_starts_with($qrCode->logo_path, "qr-logos/{$qrCode->workspace_id}/")
            && $disk->exists($qrCode->logo_path),
            404,
        );

        return $disk->path($qrCode->logo_path);
    }

    private function color(string $hex): Color
    {
        abort_unless(preg_match('/\A#[0-9A-Fa-f]{6}\z/', $hex) === 1, 404);

        return new Color(
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        );
    }

    private function jpeg(string $png): string
    {
        $image = imagecreatefromstring($png);
        if (! $image instanceof \GdImage) {
            throw new RuntimeException('The PNG QR image could not be converted to JPEG.');
        }

        ob_start();
        try {
            if (! imagejpeg($image, null, 90)) {
                throw new RuntimeException('The QR image could not be encoded as JPEG.');
            }
            $jpeg = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! is_string($jpeg)) {
            throw new RuntimeException('The QR image could not be encoded as JPEG.');
        }

        return $jpeg;
    }
}
