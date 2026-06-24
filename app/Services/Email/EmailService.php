<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Models\PlatformSetting;
use App\Support\Config\AppSecrets;
use App\Support\Email\EmailHtmlLayout;
use App\Support\Email\EmailTemplateDefaults;
use App\Support\Email\EmailTemplatePlaceholders;
use App\Support\Email\EmailTemplateVars;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailService
{
    /**
     * @param  array<string, string>  $vars
     */
    public function sendTemplateEmail(string $key, string $to, array $vars): ?array
    {
        $template = EmailTemplate::query()->find($key);

        if ($template === null) {
            return $this->dispatch($to, $key, json_encode($vars, JSON_THROW_ON_ERROR));
        }

        $vars = EmailTemplateVars::enrich($key, $vars, $to);
        $subject = $this->interpolate($template->subject, $vars);
        $bodyText = $this->buildTemplateText($key, $template->bodyText, $vars);
        $bodyHtml = $this->buildTemplateHtml($key, $template->bodyHtml, $vars);
        $headline = EmailTemplateDefaults::headlineFor($key);

        return $this->dispatch($to, $subject, $bodyText, null, $bodyHtml, $headline);
    }

    /**
     * @param  array{to: string, resetLink: string}  $params
     */
    public function sendPasswordResetEmail(array $params): ?array
    {
        $sent = $this->sendTemplateEmail('password_reset', $params['to'], [
            'resetLink' => $params['resetLink'],
        ]);

        if ($sent !== null && ($sent['sent'] ?? false)) {
            return $sent;
        }

        $subject = 'Reset your password';
        $body = implode("\n", [
            'We received a request to reset your password.',
            '',
            'Reset your password: '.$params['resetLink'],
            '',
            'This link expires in 1 hour. If you did not request this, you can ignore this email.',
            '',
            'Thank you for shopping with BNF Asia.',
        ]);

        return $this->dispatch($params['to'], $subject, $body);
    }

    /**
     * @param  array{to: string, orderNumber: string, paymentMethod: string, totalAmount: float, currency: string, invoicePdf?: string, customerName?: string, orderDate?: string}  $params
     */
    public function sendOrderConfirmationEmail(array $params): ?array
    {
        $symbol = $params['currency'] === 'USD' ? '$' : '₱';
        $total = $symbol.number_format($params['totalAmount'], 2, '.', ',');
        $attachments = isset($params['invoicePdf'])
            ? [['filename' => "invoice-{$params['orderNumber']}.pdf", 'content' => $params['invoicePdf']]]
            : null;

        $template = EmailTemplate::query()->find('order_confirmation');
        if ($template !== null) {
            $vars = EmailTemplateVars::enrich('order_confirmation', array_filter([
                'orderNumber' => $params['orderNumber'],
                'paymentMethod' => $params['paymentMethod'],
                'total' => $total,
                'customerName' => $params['customerName'] ?? '',
                'orderDate' => $params['orderDate'] ?? now()->format('F j, Y'),
            ]), $params['to']);

            $subject = $this->interpolate($template->subject, $vars);
            $bodyText = $this->buildTemplateText('order_confirmation', $template->bodyText, $vars);
            $bodyHtml = $this->buildTemplateHtml('order_confirmation', $template->bodyHtml, $vars);
            $headline = EmailTemplateDefaults::headlineFor('order_confirmation');
            if ($attachments !== null) {
                $bodyText .= "\n\nYour invoice is attached to this email as a PDF.";
            }

            return $this->dispatch($params['to'], $subject, $bodyText, $attachments, $bodyHtml, $headline);
        }

        $subject = "Order confirmed — {$params['orderNumber']}";
        $body = implode("\n", array_filter([
            "Thank you for your order {$params['orderNumber']}.",
            'Payment method: '.str_replace('_', ' ', $params['paymentMethod']),
            "Total: {$total}",
            '',
            $attachments !== null ? 'Your invoice is attached to this email as a PDF.' : '',
            'Thank you for shopping with BNF Asia.',
        ]));

        return $this->dispatch($params['to'], $subject, $body, $attachments);
    }

    /**
     * @param  array{to: string, orderNumber: string, carrier: string, trackingNumber: string, customerName?: string, orderDate?: string}  $params
     */
    public function sendOrderShippedEmail(array $params): ?array
    {
        return $this->sendTemplateEmail('order_shipped', $params['to'], array_filter([
            'orderNumber' => $params['orderNumber'],
            'carrier' => $params['carrier'],
            'trackingNumber' => $params['trackingNumber'],
            'customerName' => $params['customerName'] ?? '',
            'orderDate' => $params['orderDate'] ?? '',
        ]));
    }

    /**
     * @param  array{to: string, orderNumber: string, shippingStatus: string, paymentStatus: string, customerName?: string, orderDate?: string}  $params
     */
    public function sendOrderStatusEmail(array $params): ?array
    {
        $sent = $this->sendTemplateEmail('order_status', $params['to'], array_filter([
            'orderNumber' => $params['orderNumber'],
            'shippingStatus' => $params['shippingStatus'],
            'paymentStatus' => $params['paymentStatus'],
            'customerName' => $params['customerName'] ?? '',
            'orderDate' => $params['orderDate'] ?? '',
        ]));

        if ($sent !== null && ($sent['sent'] ?? false)) {
            return $sent;
        }

        $subject = "Order {$params['orderNumber']} — status update";
        $body = implode("\n", [
            "Your order {$params['orderNumber']} has been updated.",
            "Shipping: {$params['shippingStatus']}",
            "Payment: {$params['paymentStatus']}",
            '',
            'Thank you for shopping with BNF Asia.',
        ]);

        return $this->dispatch($params['to'], $subject, $body);
    }

    /**
     * @param  array{to: string, orderNumber: string, totalAmount: float, paymentMethod: string, accountUrl: string, customerName?: string, orderDate?: string}  $params
     */
    public function sendPaymentReminderEmail(array $params): ?array
    {
        $total = '₱'.number_format($params['totalAmount'], 2, '.', ',');
        $sent = $this->sendTemplateEmail('payment_reminder', $params['to'], array_filter([
            'orderNumber' => $params['orderNumber'],
            'total' => $total,
            'paymentMethod' => $params['paymentMethod'],
            'accountUrl' => $params['accountUrl'],
            'customerName' => $params['customerName'] ?? '',
            'orderDate' => $params['orderDate'] ?? '',
        ]));

        if ($sent !== null && ($sent['sent'] ?? false)) {
            return $sent;
        }

        $subject = "Payment reminder — order {$params['orderNumber']}";
        $body = implode("\n", [
            "Your order {$params['orderNumber']} is ready for payment.",
            "Total: {$total}",
            'Payment method: '.str_replace('_', ' ', $params['paymentMethod']),
            '',
            "View your order and chat with us: {$params['accountUrl']}",
            '',
            'Thank you for shopping with BNF Asia.',
        ]);

        return $this->dispatch($params['to'], $subject, $body);
    }

    /**
     * @param  array{to: string, productName: string, productUrl: string}  $params
     */
    public function sendStockAlertEmail(array $params): ?array
    {
        $subject = "{$params['productName']} is back in stock";
        $body = implode("\n", [
            "Good news — {$params['productName']} is available again.",
            '',
            "Shop now: {$params['productUrl']}",
            '',
            'Thank you for shopping with BNF Asia.',
        ]);

        return $this->dispatch($params['to'], $subject, $body);
    }

    public function sendTestEmail(string $to): ?array
    {
        return $this->dispatch(
            $to,
            'BNF Asia — test email',
            'This is a test email from your BNF Asia store. If you received this, SMTP is configured correctly.',
        );
    }

    public function sendTemplateTestEmail(string $key, string $to): ?array
    {
        $template = EmailTemplate::query()->find($key);
        if ($template === null) {
            throw new NotFoundHttpException("Email template not found: {$key}");
        }

        return $this->sendTemplateEmail($key, $to, EmailTemplateVars::sampleVars($key, $to));
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function buildTemplateHtml(string $key, ?string $templateHtml, array $vars): ?string
    {
        $raw = is_string($templateHtml) && trim($templateHtml) !== ''
            ? $templateHtml
            : EmailTemplateDefaults::htmlFor($key);

        if ($raw === null) {
            return null;
        }

        $expanded = EmailTemplatePlaceholders::expandHtmlFragments($key, $raw, $vars);

        return $this->interpolate($expanded, $vars);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function buildTemplateText(string $key, string $templateText, array $vars): string
    {
        $expanded = EmailTemplatePlaceholders::expandTextFragments($key, $templateText, $vars);

        return $this->interpolate($expanded, $vars);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function interpolate(string $template, array $vars): string
    {
        $result = $template;

        foreach ($vars as $key => $value) {
            $result = str_replace('{{'.$key.'}}', $value, $result);
        }

        return $result;
    }

    /**
     * @param  array<int, array{filename: string, content: string}>|null  $attachments
     * @return array{sent: bool, subject: string, preview: string, channel: string, error?: string}|null
     */
    private function dispatch(
        string $to,
        string $subject,
        string $bodyText,
        ?array $attachments = null,
        ?string $bodyHtml = null,
        ?string $headline = null,
    ): ?array {
        if ($bodyHtml === null || trim($bodyHtml) === '') {
            $bodyHtml = EmailHtmlLayout::plainTextToHtml($bodyText);
        }

        if (trim($bodyHtml) !== '') {
            $brandName = PlatformSetting::query()->find('default')?->storeName ?? 'BNF Asia';
            $bodyHtml = EmailHtmlLayout::wrap($subject, $bodyHtml, $brandName, $headline);
        }

        if (AppSecrets::isSmtpConfigured()) {
            try {
                if ($bodyHtml !== null && trim($bodyHtml) !== '') {
                    Mail::send([], [], static function ($message) use ($to, $subject, $bodyText, $bodyHtml, $attachments): void {
                        $smtp = AppSecrets::getSmtpConfig();
                        if ($smtp['from']) {
                            $message->from($smtp['from']);
                        }
                        $message->to($to)->subject($subject);
                        $message->html($bodyHtml);
                        $message->text($bodyText);
                        foreach ($attachments ?? [] as $file) {
                            $message->attachData($file['content'], $file['filename'], ['mime' => 'application/pdf']);
                        }
                    });
                } else {
                    Mail::raw($bodyText, static function ($message) use ($to, $subject, $attachments): void {
                        $smtp = AppSecrets::getSmtpConfig();
                        if ($smtp['from']) {
                            $message->from($smtp['from']);
                        }
                        $message->to($to)->subject($subject);
                        foreach ($attachments ?? [] as $file) {
                            $message->attachData($file['content'], $file['filename'], ['mime' => 'application/pdf']);
                        }
                    });
                }

                return ['sent' => true, 'subject' => $subject, 'preview' => $bodyHtml ?? $bodyText, 'channel' => 'smtp'];
            } catch (\Throwable $e) {
                Log::error("SMTP failed for {$to}: {$e->getMessage()}");

                return [
                    'sent' => false,
                    'subject' => $subject,
                    'preview' => $bodyHtml ?? $bodyText,
                    'channel' => 'smtp',
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (app()->environment('production')) {
            Log::error("Email not sent (SMTP not configured): {$to} | {$subject}");

            return ['sent' => false, 'subject' => $subject, 'preview' => $bodyHtml ?? $bodyText, 'channel' => 'none'];
        }

        Log::info("[email] To: {$to} | {$subject}\n".($bodyHtml ?? $bodyText));

        return ['sent' => true, 'subject' => $subject, 'preview' => $bodyHtml ?? $bodyText, 'channel' => 'log'];
    }
}
