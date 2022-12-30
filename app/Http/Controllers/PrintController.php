<?php

namespace App\Http\Controllers;

use App\Models\Order;
use charlieuki\ReceiptPrinter\ReceiptPrinter as ReceiptPrinter;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $only = $request->only([
            'order_uuid'
        ]);

        $order = Order::findOrFail($only['order_uuid']);

        try{
            // Set params
            $mid = null;
            $store_name = 'Billiard Store';
            $store_address = 'Jalan Setia Ujung Delitua';
            $store_phone = '081234567890';
            $store_email = 'store@gmail.com';
            $store_website = 'billiard-store.com';
//            $tax_percentage = 10;
            $transaction_id = $order->order_number;
            $currency = 'Rp';
//            $image_path = 'logo.png';

            // Set items
            $items = [];
            foreach ($order->orderItems as $item) {
                $items[] = [
                    'name' => $item->product->name . ' - ' . $item?->activeOrder?->duration . ' Menit',
                    'qty' => $item->quantity,
                    'price' => $item->product->type == 'Billiard' ? $item->price : $item->product->price,
                ];
            }

            // Init printer
            $printer = new ReceiptPrinter;
            $printer->init(
                config('receiptprinter.connector_type'),
                config('receiptprinter.connector_descriptor')
            );

            // Set store info
            $printer->setStore($mid, $store_name, $store_address, $store_phone, $store_email, $store_website);

            // Set currency
            $printer->setCurrency($currency);

            // Add items
            foreach ($items as $item) {
                $printer->addItem(
                    $item['name'],
                    $item['qty'],
                    $item['price']
                );
            }

            // Set tax
//            $printer->setTax($tax_percentage);

            // Calculate total
            $printer->calculateSubTotal();
            $printer->calculateGrandTotal();

            // Set transaction ID
            $printer->setTransactionID($transaction_id);

            // Set logo
            // Uncomment the line below if $image_path is defined
            //$printer->setLogo($image_path);

            // Set QR code
//            $printer->setQRcode([
//                'tid' => $transaction_id,
//            ]);

            // Print receipt
            $printer->printReceipt();

            return redirect()->back()->with('success', 'Print success');
        }catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
