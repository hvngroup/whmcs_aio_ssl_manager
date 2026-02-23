<?php

namespace AioSSL\Service;

use WHMCS\Database\Capsule;

class NotificationService
{
    /**
     * Send certificate issuance notification
     */
    public function notifyIssuance($order, array $configdata): void
    {
        $domain = $configdata['domains'][0] ?? 'Unknown';
        $provider = $configdata['provider'] ?? 'Unknown';

        $subject = "SSL Certificate Issued: {$domain}";
        $body = $this->buildHtmlEmail('issuance', [
            'domain'   => $domain,
            'provider' => strtoupper($provider),
            'certtype' => $order->certtype ?? '',
            'order_id' => $order->id,
            'end_date' => $configdata['end_date'] ?? 'N/A',
        ]);

        $this->sendAdmin($subject, $body);
    }

    /**
     * Send expiry warning notifications
     */
    public function sendExpiryWarnings(): void
    {
        $days = (int)(Capsule::table('mod_aio_ssl_settings')
            ->where('setting', 'notify_expiry_days')
            ->value('value') ?: 30);

        $expiring = Capsule::table('tblsslorders')
            ->whereIn('status', ['Completed', 'Active', 'Issued'])
            ->whereRaw("JSON_EXTRACT(configdata, '$.end_date') <= ?", [date('Y-m-d', strtotime("+{$days} days"))])
            ->whereRaw("JSON_EXTRACT(configdata, '$.end_date') >= ?", [date('Y-m-d')])
            ->get();

        foreach ($expiring as $order) {
            $configdata = json_decode($order->configdata, true) ?: [];
            $endDate = $configdata['end_date'] ?? '';
            $daysLeft = (int)((strtotime($endDate) - time()) / 86400);
            $urgency = $daysLeft <= 7 ? 'critical' : 'warning';
            $domain = $configdata['domains'][0] ?? 'Unknown';

            $icon = $daysLeft <= 7 ? '🚨' : '⚠️';
            $subject = "{$icon} SSL Expiring in {$daysLeft}d: {$domain}";
            $body = $this->buildHtmlEmail('expiry', [
                'domain'    => $domain,
                'end_date'  => $endDate,
                'days_left' => $daysLeft,
                'urgency'   => $urgency,
                'order_id'  => $order->id,
                'certtype'  => $order->certtype,
            ]);

            $this->sendAdmin($subject, $body);
        }
    }

    /**
     * Send sync error notification
     */
    public function notifySyncErrors(array $errors): void
    {
        if (empty($errors)) return;

        $subject = '🔴 AIO SSL: Sync Errors Detected';
        $body = $this->buildHtmlEmail('sync_error', ['errors' => $errors]);
        $this->sendAdmin($subject, $body);
    }

    /**
     * Send admin email via WHMCS Local API
     */
    private function sendAdmin(string $subject, string $body): void
    {
        $email = Capsule::table('mod_aio_ssl_settings')
            ->where('setting', 'notify_admin_email')
            ->value('value');

        if (empty($email)) {
            // Use first admin email
            $admin = Capsule::table('tbladmins')->where('disabled', 0)->first();
            $email = $admin ? $admin->email : '';
        }

        if (empty($email)) return;

        try {
            localAPI('SendEmail', [
                'customtype'   => 'general',
                'customsubject'=> $subject,
                'custommessage'=> $body,
                'customvars'   => base64_encode(serialize(['to' => $email])),
            ]);
        } catch (\Exception $e) {
            // Fallback: PHP mail
            @mail($email, $subject, $body, "Content-Type: text/html; charset=UTF-8\r\n");
        }
    }

    /**
     * Build HTML email from template type
     */
    private function buildHtmlEmail(string $type, array $vars): string
    {
        $header = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
        $header .= '<div style="background:#1890ff;color:#fff;padding:15px 20px;border-radius:6px 6px 0 0;">';
        $header .= '<h2 style="margin:0;">🛡️ HVN — AIO SSL Manager</h2></div>';
        $header .= '<div style="border:1px solid #d9d9d9;border-top:0;padding:20px;border-radius:0 0 6px 6px;">';

        $footer = '<hr style="border:0;border-top:1px solid #eee;margin:20px 0;">';
        $footer .= '<p style="font-size:11px;color:#999;">This notification was sent by AIO SSL Manager v' . AIO_SSL_VERSION . '</p>';
        $footer .= '<p style="font-size:11px;color:#999;">Powered by <a href="https://hvn.vn" style="color:#1890ff;">HVN GROUP</a></p>';
        $footer .= '</div></div>';

        $content = '';
        switch ($type) {
            case 'issuance':
                $content = '<h3 style="color:#52c41a;">✅ Certificate Issued</h3>'
                    . '<table style="width:100%;border-collapse:collapse;">'
                    . '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($vars['domain']) . '</td></tr>'
                    . '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Provider:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($vars['provider']) . '</td></tr>'
                    . '<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Type:</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($vars['certtype']) . '</td></tr>'
                    . '<tr><td style="padding:8px;"><strong>Expires:</strong></td><td style="padding:8px;">' . htmlspecialchars($vars['end_date']) . '</td></tr>'
                    . '</table>';
                break;

            case 'expiry':
                $bgColor = ($vars['urgency'] === 'critical') ? '#ff4d4f' : '#faad14';
                $content = '<div style="background:' . $bgColor . ';color:#fff;padding:10px 15px;border-radius:4px;margin-bottom:15px;">'
                    . '<strong>' . ($vars['days_left'] <= 7 ? '🚨 CRITICAL' : '⚠️ WARNING') . '</strong>: Certificate expires in ' . $vars['days_left'] . ' days</div>'
                    . '<p><strong>Domain:</strong> ' . htmlspecialchars($vars['domain']) . '</p>'
                    . '<p><strong>Expires:</strong> ' . htmlspecialchars($vars['end_date']) . '</p>';
                break;

            case 'sync_error':
                $content = '<h3 style="color:#ff4d4f;">🔴 Sync Errors</h3><ul>';
                foreach ($vars['errors'] as $slug => $err) {
                    $content .= '<li><strong>' . htmlspecialchars($slug) . ':</strong> ' . htmlspecialchars(is_array($err) ? ($err['error'] ?? '') : $err) . '</li>';
                }
                $content .= '</ul>';
                break;
        }

        return $header . $content . $footer;
    }
}