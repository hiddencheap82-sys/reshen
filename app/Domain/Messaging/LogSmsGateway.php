<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Dev/staging driver: writes to storage/logs/sms.log instead of a real
 * carrier. Lets the whole OTP + reminder flow be exercised without
 * Melipayamak/Kavenegar credentials.
 */
final class LogSmsGateway implements SmsGatewayInterface
{
    public function send(string $e164Phone, string $message): array
    {
        return $this->write($e164Phone, $message);
    }

    public function sendPattern(string $e164Phone, string $patternId, array $args): array
    {
        return $this->write($e164Phone, sprintf('[الگو %s] %s', $patternId, implode(' | ', $args)));
    }

    private function write(string $e164Phone, string $message): array
    {
        $line = sprintf("[%s] -> %s: %s\n", date('Y-m-d H:i:s'), $e164Phone, $message);
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents($logDir . '/sms.log', $line, FILE_APPEND);

        return ['ok' => true, 'ref' => 'log-' . bin2hex(random_bytes(4)), 'error' => null];
    }

    public function name(): string
    {
        return 'log';
    }
}
