<?php

namespace App\Services;

use App\Models\Payment;

class InvoiceNumberGeneratorService
{
    /**
     * Generate a custom invoice number with the pattern IPRI{yyyymmdd}{4-digit sequence}
     *
     * @return string
     */
    public static function generate()
    {
        $date = now()->format('Ymd');
        $prefix = 'IPRI' . $date;

        // Get the latest invoice number for today
        $latestPayment = Payment::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($latestPayment) {
            // Extract the sequence number from the latest invoice
            $lastSequence = substr($latestPayment->invoice_number, -4);
            $sequence = str_pad((int)$lastSequence + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // Start with 0001 for today
            $sequence = '0001';
        }

        return $prefix . $sequence;
    }
}
