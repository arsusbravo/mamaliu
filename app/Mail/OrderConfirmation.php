<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $orders;
    public $week;
    public $year;
    public $total;
    public $totalDiscount;
    public $groupName;

    public function __construct($user, $orders, $week, $year)
    {
        $this->user = $user;
        $this->orders = $orders;
        $this->week = $week;
        $this->year = $year;

        // Calculate total (post-discount) and how much was discounted
        $this->total = collect($orders)->sum(fn ($order) => $order->lineTotal());
        $this->totalDiscount = collect($orders)->sum(fn ($order) => $order->lineDiscount());

        // Get group name from the first non-gift order (gift lines have no weekmenu)
        $firstRealOrder = collect($orders)->first(fn ($order) => !$order->is_gift) ?? collect($orders)->first();
        $this->groupName = $firstRealOrder?->weekmenu?->group?->name ?? 'General delivery area';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation - Week {$this->week}, {$this->year}",
            bcc: [
                new Address(config('mail.owner'), 'Mama Liu'),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}