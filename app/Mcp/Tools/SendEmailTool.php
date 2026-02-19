<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Mail;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SendEmailTool extends Tool
{
    protected string $name = 'send_email';

    protected string $description = <<<'MARKDOWN'
        Send an email via Gmail SMTP.

        Required fields: to, subject, content.
        Optional fields: cc, bcc.

        to, cc, and bcc accept comma-separated email addresses (e.g. "a@example.com, b@example.com").
        content supports plain text. Keep it concise and relevant.

        Returns confirmation with recipient details.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $to = $this->parseEmails($request->get('to', ''));
        if (empty($to)) {
            return Response::error('At least one valid "to" email address is required.');
        }

        $subject = trim($request->get('subject', ''));
        if ($subject === '') {
            return Response::error('Subject is required.');
        }

        $content = trim($request->get('content', ''));
        if ($content === '') {
            return Response::error('Email content is required.');
        }

        $cc = $this->parseEmails($request->get('cc', '') ?? '');
        $bcc = $this->parseEmails($request->get('bcc', '') ?? '');

        try {
            Mail::raw($content, function ($message) use ($to, $cc, $bcc, $subject) {
                $message->to($to)
                    ->subject($subject);

                if (! empty($cc)) {
                    $message->cc($cc);
                }

                if (! empty($bcc)) {
                    $message->bcc($bcc);
                }
            });

            $result = [
                'message' => 'Email sent successfully',
                'to' => $to,
                'subject' => $subject,
            ];

            if (! empty($cc)) {
                $result['cc'] = $cc;
            }
            if (! empty($bcc)) {
                $result['bcc'] = $bcc;
            }

            return Response::text(json_encode($result, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return Response::error("Failed to send email: {$e->getMessage()}");
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()->required()->description('Recipient email address(es), comma-separated for multiple'),
            'cc' => $schema->string()->nullable()->description('CC email address(es), comma-separated for multiple'),
            'bcc' => $schema->string()->nullable()->description('BCC email address(es), comma-separated for multiple'),
            'subject' => $schema->string()->required()->description('Email subject line'),
            'content' => $schema->string()->required()->description('Email body content (plain text)'),
        ];
    }

    /**
     * Parse a comma-separated string of emails into a clean array.
     */
    protected function parseEmails(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
