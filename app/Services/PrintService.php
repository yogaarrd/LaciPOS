<?php
namespace App\Services;

use App\Models\Order;
use App\Models\StoreProfile;
use Illuminate\Support\Facades\Log;

class PrintService
{
    private ReceiptService $receiptService;

    public function __construct(ReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    public function printReceipt(Order $order, bool $withoutLogo = false): array
    {
        if (!config('nativephp-internal.running')) {
            return ['success' => false, 'message' => 'Cetak langsung tersedia di aplikasi desktop LaciPOS. Buka aplikasi desktop atau unduh struk PDF.'];
        }

        $dispatched = false;
        $store = StoreProfile::getProfile();
        $paperSize = $store?->paper_size ?? '58mm';

        try {
            $html = $this->receiptService->renderReceiptHtml($order, $paperSize, $withoutLogo);
            
            // Encode only specific characters that break data: URIs natively
            // '#' breaks the URL (acts as fragment) and '%' causes invalid encoding exception
            // Also STRIP ALL NEWLINES because unescaped newlines in a data URI will cause
            // Chromium's URL parser to silently abort or truncate the document!
            $html = str_replace(["\r", "\n"], '', $html);
            $html = str_replace(['%', '#'], ['%25', '%23'], $html);

            // Keep the saved OS name, including manually configured printers.
            // Discovery failure must not prevent an otherwise working print.
            $payload = [
                'html' => $html,
                'printer' => $store?->printer_name ?? '',
                'settings' => [
                    'silent' => true,
                    'printBackground' => false,
                    'margins' => ['marginType' => 'none'],
                    'pageSize' => [
                        'width' => $paperSize === '58mm' ? 58000 : 80000,
                        'height' => 3276000,
                    ],
                ],
            ];
            $omittedLogo = false;
            // NativePHP's bodyParser.json() accepts 100 KiB by default.
            if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 102400 && !$withoutLogo) {
                $plainHtml = $this->receiptService->renderReceiptHtml($order, $paperSize, true);
                $payload['html'] = str_replace(['%', '#'], ['%25', '%23'], str_replace(["\r", "\n"], '', $plainHtml));
                $omittedLogo = true;
            }
            if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 102400) {
                return ['success' => false, 'message' => 'Struk terlalu panjang untuk cetak langsung. Unduh struk PDF dan cetak melalui pembaca PDF.'];
            }
            $dispatched = true;
            $response = app(PrinterClient::class)->post('system/print', $payload);

            if (!$response->successful()) {
                Log::warning('Receipt print rejected', ['status' => $response->status()]);
                return ['success' => false, 'message' => 'Sistem cetak menolak permintaan. Periksa printer dan ukuran kertas di pengaturan, lalu lakukan tes cetak.'];
            }

            return ['success' => true, 'message' => $omittedLogo ? 'Permintaan cetak diterima tanpa logo karena ukuran struk terlalu besar. Periksa hasil pada printer.' : 'Permintaan cetak diterima sistem. Periksa hasil pada printer.'];
        } catch (\Throwable $e) {
            Log::error('Print error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => !$dispatched ? 'Struk gagal disiapkan. Periksa informasi toko dan coba tes tanpa logo.' : 'Status cetak belum dapat dipastikan. Periksa printer dan antrean cetak sebelum mencoba lagi agar struk tidak tercetak dua kali.',
            ];
        }
    }
}
 