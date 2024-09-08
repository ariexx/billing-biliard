<?php

namespace App\Http\Controllers;

use App\Models\Order;
use charlieuki\ReceiptPrinter\ReceiptPrinter as ReceiptPrinter;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    private function formatText($text, $isItem = false) {
        $paperSize = config('receiptprinter.paper_size');
        $maxWidth = $paperSize === '58mm' ? 32 : 42; // Set max width based on paper size
        if ($isItem && $paperSize === '80mm') {
            $maxWidth = 48; // Increase width for items on 80mm paper
        }
        $lines = explode("\n", wordwrap($text, $maxWidth, "\n", true));
        return implode("\n", $lines);
    }

    private function addItemWithAlignment($printer, $name, $qty, $price) {
        $paperSize = config('receiptprinter.paper_size');
        $maxWidth = $paperSize === '58mm' ? 32 : 42; // Set max width based on paper size
        $name = str_pad($name, $maxWidth, ' ', STR_PAD_BOTH); // Center align the name
        $printer->addItem($name, $qty, $price);
    }

    public function __invoke(Request $request)
    {
        $only = $request->only(['order_uuid']);
        $order = Order::findOrFail($only['order_uuid']);

        try {
            $mid = null;
            $store_name = 'Black Dragon Pool';
            $store_address = 'Jalan Kapten Pattimura No.93, Lubuk Pakam';
            $store_phone = null;
            $store_email = null;
            $store_website = null;
            $transaction_id = $order->order_number;
            $currency = 'Rp';

            $items = [];
            foreach ($order->orderItems as $item) {
                $items[] = [
                    'name' => $item->product->name . ' - ' . $item?->hour . ' Jam',
                    'qty' => $item->quantity,
                    'price' => $item->product->type == 'Billiard' ? $item->price : $item->product->price,
                ];
            }

            $printer = new ReceiptPrinter;
            $printer->init(
                config('receiptprinter.connector_type'),
                config('receiptprinter.connector_descriptor')
            );

            $printer->setStore($mid, $this->formatText($store_name), $this->formatText($store_address), $store_phone, $store_email, $store_website);
            $printer->setCurrency($currency);

            foreach ($items as $item) {
                $this->addItemWithAlignment($printer, $item['name'], $item['qty'], $item['price']);
            }

            $printer->calculateSubTotal();
            $printer->calculateGrandTotal();
            $printer->setTransactionID($transaction_id);
            $printer->printReceipt();

            $order->update(['print_count' => $order->print_count + 1]);

            \Log::channel('daily')->info("Print Success: Order Number : {$order->order_number} - Total Print : {$order->print_count} - Printed At : " . now()->format("Y-m-d H:i:s") . " - Printed By : " . auth()->user()->name);

            return redirect()->back()->with('success', 'Print success');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
