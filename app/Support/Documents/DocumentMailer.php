<?php

namespace App\Support\Documents;

use App\Enums\EmailTemplateType;
use App\Mail\DocumentMail;
use App\Models\Department;
use App\Models\EmailTemplate;
use App\Models\SalesDocument;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends documents (quotations, invoices, work orders…) to customers by e-mail and keeps a copy of every e-mail.
 */
class DocumentMailer
{
    public function __construct(private DocumentPdf $pdf)
    {
        //
    }

    /**
     * Get the pre-filled e-mail (recipient, subject and body) for a document.
     *
     * @param  array<string, string|int|float|null>  $placeholders
     * @return array{to: string, cc: string, subject: string, body: string}
     */
    public function draft(SalesDocument $document, EmailTemplateType $type, array $placeholders): array
    {
        return $this->compose(
            $document->team_id,
            $type,
            $placeholders,
            (string) ($document->customer_email ?? ''),
            $document->documentTitle().' '.$document->number,
        );
    }

    /**
     * Pre-fill an e-mail from the team's template of the given type.
     *
     * @param  array<string, string|int|float|null>  $placeholders
     * @return array{to: string, cc: string, subject: string, body: string}
     */
    public function compose(int $teamId, EmailTemplateType $type, array $placeholders, string $to, string $fallbackSubject): array
    {
        $template = EmailTemplate::query()
            ->where('team_id', $teamId)
            ->where('type', $type)
            ->orderByDesc('is_default')
            ->first();

        $rendered = $template?->render($placeholders) ?? ['subject' => $fallbackSubject, 'body' => ''];

        return [
            'to' => $to,
            'cc' => '',
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ];
    }

    /**
     * Send the document. A failed delivery is recorded (and returned) instead of thrown.
     *
     * @param  array{to: string, cc?: string|null, subject: string, body: string, attach_pdf?: bool}  $message
     */
    public function send(SalesDocument $document, User $sender, array $message): SentEmail
    {
        $attach = $message['attach_pdf'] ?? true;

        return $this->deliver(
            $document,
            $document->department ?? $document->team()->firstOrFail()->defaultDepartment(),
            $sender,
            $message,
            $attach ? $this->pdf->render($document) : null,
            $this->pdf->filename($document),
        );
    }

    /**
     * Send an e-mail about a record (with a PDF, if given) and keep a copy on the record.
     * A failed delivery is recorded (and returned) instead of thrown.
     *
     * @param  array{to: string, cc?: string|null, subject: string, body: string}  $message
     */
    public function deliver(Model $record, ?Department $department, User $sender, array $message, ?string $pdf, string $filename): SentEmail
    {
        $to = $this->addresses($message['to']);
        $cc = $this->addresses($message['cc'] ?? '');

        $mail = new DocumentMail(
            subjectLine: $message['subject'],
            body: $message['body'],
            senderName: $department?->displayName() ?? (string) config('app.name'),
            replyToAddress: $department->email ?? $sender->email,
            pdf: $pdf,
            pdfName: $filename,
        );

        $status = 'sent';
        $error = null;

        try {
            Mail::to($to)->cc($cc)->send($mail);
        } catch (Throwable $e) {
            report($e);
            $status = 'failed';
            $error = Str::limit($e->getMessage(), 500);
        }

        $sentEmail = new SentEmail([
            'team_id' => $record->getAttribute('team_id'),
            'user_id' => $sender->id,
            'to' => implode(', ', $to),
            'cc' => $cc === [] ? null : implode(', ', $cc),
            'subject' => $message['subject'],
            'body' => $message['body'],
            'attachments' => $pdf !== null ? [$filename] : [],
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
        $sentEmail->emailable()->associate($record);
        $sentEmail->save();

        return $sentEmail;
    }

    /**
     * Split a comma or semicolon separated list of addresses.
     *
     * @return array<int, string>
     */
    public function addresses(?string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value) ?: [])));
    }
}
