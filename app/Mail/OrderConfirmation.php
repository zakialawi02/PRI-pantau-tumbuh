<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public Payment $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Add payment method to subject for better clarity
        $paymentMethod = '';
        if ($this->payment->payment_method === 'bank_transfer') {
            $paymentMethod = 'Bank Transfer';
        } elseif ($this->payment->payment_method === 'paypal') {
            $paymentMethod = 'PayPal';
        } else {
            $paymentMethod = ucfirst(str_replace('_', ' ', $this->payment->payment_method));
        }

        return new Envelope(
            subject: 'Order Confirmation - #' . $this->payment->invoice_number ?? substr($this->payment->id, 0, 16) . ' (' . $paymentMethod . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'payment' => $this->payment,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
