<?php

namespace App\Domain\Operations;

class ProductionMail
{
    public static function configured(?string $name = null, array $visited = []): bool
    {
        $name ??= config('mail.default');
        if (! $name || in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? '';
        if (in_array($transport, ['failover', 'roundrobin'])) {
            $children = $mailer['mailers'] ?? [];

            return count($children) > 0 && collect($children)->every(fn ($child) => self::configured($child, [...$visited, $name]));
        }
        if ($transport !== 'smtp') {
            return false;
        } // Only the supplied SMTP deployment contract is verified here.
        $host = $mailer['host'] ?? '';
        $from = config('mail.from.address', '');

        return is_string($host) && trim($host) !== '' && ! preg_match('/(?:localhost|127\.0\.0\.1|\.invalid|\.example)$/i', $host)
            && (int) ($mailer['port'] ?? 0) >= 1 && (int) ($mailer['port'] ?? 0) <= 65535
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false && ! preg_match('/@example\.(com|org|net)$/i', $from);
    }
}
