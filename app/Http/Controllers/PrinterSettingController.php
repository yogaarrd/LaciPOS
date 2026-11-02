<?php

namespace App\Http\Controllers;

use App\Models\StoreProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PrinterSettingController extends Controller
{
    public function index()
    {
        $profile = StoreProfile::getProfile();
        
        $printers = [];
        $printerStatus = 'desktop_required';
        if (config('nativephp-internal.running')) {
            try {
                $response = app(\App\Services\PrinterClient::class)->get('system/printers');
                $nativePrinters = $response->json('printers');
                if (!$response->successful() || !is_array($nativePrinters)) {
                    throw new \RuntimeException('Printer list unavailable');
                }
                foreach ($nativePrinters as $printer) {
                    if (!is_string($printer['name'] ?? null)) { continue; }
                    $printers[] = [
                        'name' => $printer['name'],
                        'displayName' => $printer['displayName'] ?? $printer['name'],
                        'isDefault' => (bool) ($printer['isDefault'] ?? false),
                    ];
                }
                $printerStatus = count($printers) ? 'available' : 'empty';
            } catch (\Throwable $e) {
                $printerStatus = 'unavailable';
                Log::warning('Could not fetch printers from NativePHP: ' . $e->getMessage());
            }
        }

        return Inertia::render('Settings/Printer', [
            'profile' => $profile,
            'printers' => $printers,
            'printerStatus' => $printerStatus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'printer_name' => 'nullable|string|max:255',
            'paper_size' => 'required|in:58mm,80mm',
            'auto_print' => 'required|boolean',
        ]);

        $profile = StoreProfile::getProfile();
        if (!$profile) {
            $profile = StoreProfile::create($validated);
        } else {
            $profile->update($validated);
        }

        return redirect()->back()->with('success', 'Pengaturan printer berhasil disimpan.');
    }

    public function testPrint(Request $request, \App\Services\PrintService $printService)
    {
        $dummyUser = new \App\Models\User(['name' => 'Kasir Test']);
        $dummyUser->id = 'test-user-id';

        $makeItem = function (string $name, ?string $variant, int $qty, int $price, int $discountPerUnit, int $subtotal) {
            $item = new \App\Models\OrderItem();
            $item->product_name_snapshot = $name;
            $item->variant_label = $variant;
            $item->quantity = $qty;
            $item->snapshot_price = $price;
            $item->snapshot_discount_amount = $discountPerUnit;
            $item->subtotal = $subtotal;
            return $item;
        };

        $items = collect([
            $makeItem('Kopi Susu', null, 2, 12000, 2000, 20000),
            $makeItem('Es Teh Manis', null, 2, 7000, 0, 14000),
            $makeItem('Nasi Goreng', 'Spesial', 1, 25000, 0, 25000),
        ]);

        $subtotal = 20000 + 14000 + 25000; // 59.000
        $taxAmount = 5900;
        $total = $subtotal + $taxAmount; // 64.900

        $dummyOrder = new \App\Models\Order();
        $dummyOrder->order_number = 'TEST-' . date('Ymd-His');
        $dummyOrder->subtotal = $subtotal;
        $dummyOrder->payment_method = 'cash';
        $dummyOrder->payment_provider = null;
        $dummyOrder->total_amount = $total;
        $dummyOrder->cash_received = 100000;
        $dummyOrder->change_amount = 100000 - $total;
        $dummyOrder->tax_type = 'percentage';
        $dummyOrder->tax_rate = 10;
        $dummyOrder->tax_amount = $taxAmount;
        $dummyOrder->created_at = now();
        $dummyOrder->setRelation('user', $dummyUser);
        $dummyOrder->setRelation('items', $items);

        $request->validate(['without_logo' => 'sometimes|boolean']);
        $result = $printService->printReceipt($dummyOrder, $request->boolean('without_logo'));

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('warning', $result['message']);
        }
    }
}
