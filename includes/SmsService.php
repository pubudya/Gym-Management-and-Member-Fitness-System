<?php
declare(strict_types=1);

final class SmsService
{
    public static function send(string $phone, string $message, array $meta = []): array
    {
        $config = $GLOBALS['app_config']['sms'] ?? [];
        $enabled = (string) setting('sms_notifications_enabled', '1') === '1' && !empty($config['enabled']);
        $provider = $config['provider'] ?? 'demo';

        $result = [
            'status' => 'PENDING',
            'provider' => $provider,
            'provider_message_id' => null,
            'error' => null,
        ];

        if (!$enabled) {
            $result['status'] = 'SENT';
            $result['provider'] = 'disabled';
            $result['error'] = 'SMS disabled in settings; stored in database only.';
            return $result;
        }

        if ($provider === 'demo' || empty($config['api_url']) || empty($config['api_key'])) {
            $result['status'] = 'SENT';
            $result['provider'] = 'demo';
            $result['provider_message_id'] = 'DEMO-' . bin2hex(random_bytes(4));
            return $result;
        }

        try {
            $payload = json_encode([
                'to' => $phone,
                'message' => $message,
                'sender_id' => $config['sender_id'] ?? 'VOLTGYM',
                'api_key' => $config['api_key'],
            ]);
            $ch = curl_init($config['api_url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $code >= 400) {
                $result['status'] = 'FAILED';
                $result['error'] = $err ?: ('HTTP ' . $code . ' ' . $body);
                return $result;
            }
            $result['status'] = 'SENT';
            $decoded = json_decode((string) $body, true);
            $result['provider_message_id'] = $decoded['id'] ?? $decoded['message_id'] ?? null;
            return $result;
        } catch (Throwable $e) {
            $result['status'] = 'FAILED';
            $result['error'] = $e->getMessage();
            return $result;
        }
    }
}
