<?php
namespace Widia\Shipping\Services;

use Illuminate\Support\Facades\Storage;

class LabelService
{
    public static array $extensionMap = [
        'PDF' => 'pdf',
        'PNG' => 'png',
        'ZPLII' => 'zpl',
        'EPL2' => 'epl',
        'GIF' => 'gif',
    ];
    public static function saveLabel(string $imageFormat, string $encodedLabel, string $trackingNumber): string
    {
        $extension = self::$extensionMap[$imageFormat] ?? strtolower($imageFormat) ?? 'pdf';
        $datePath = date('Y/m/d');
        $fileName = config('shipping.label.directory', 'labels') . '/' . $datePath . '/' . $trackingNumber . '.' . $extension;
        $directory = dirname($fileName);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        Storage::disk('public')->put($fileName, base64_decode($encodedLabel));

        return asset(Storage::url($fileName));
    }

    public static function saveInvoice(string $encodeInvoice, string $imageFormat = 'PDF'): string
    {
        $extension = self::$extensionMap[$imageFormat] ?? strtolower($imageFormat) ?? 'pdf';

        $datePath = date('Y/m/d');
        $fileName = config('shipping.invoice.directory', 'invoices') . '/' . $datePath . '/' . uniqid('invoice_') . '.' . $extension;
        $directory = dirname($fileName);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        Storage::disk('public')->put($fileName, base64_decode($encodeInvoice));

        return asset(Storage::url($fileName));
    }
}
