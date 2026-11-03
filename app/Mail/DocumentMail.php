<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An e-mail with a document (quotation, invoice, reminder...) sent to a customer.
 */
class DocumentMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $senderName,
        public ?string $replyToAddress = null,
        public ?string $pdf = null,
        public ?string $pdfName = null,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->senderName)] : [],
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.document',
            text: 'mail.document-text',
            with: ['body' => $this->body],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }

        $pdf = $this->pdf;

        return [
            Attachment::fromData(fn () => $pdf, $this->pdfName ?? 'document.pdf')->withMime('application/pdf'),
        ];
    }
}
