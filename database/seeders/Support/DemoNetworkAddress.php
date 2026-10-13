<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

final class DemoNetworkAddress
{
    public const OPERATIONS = 'operations';

    public const INFRASTRUCTURE = 'infrastructure';

    public const DEVELOPMENT = 'development';

    /**
     * RFC 5737 TEST-NET blocks. These ranges are reserved for examples and must
     * never be routed or interpreted as addresses from the institutional network.
     *
     * @var array<string, array{int, int, int}>
     */
    private const DOCUMENTATION_NETWORKS = [
        self::OPERATIONS => [192, 0, 2],
        self::INFRASTRUCTURE => [198, 51, 100],
        self::DEVELOPMENT => [203, 0, 113],
    ];

    public static function ipv4(string $network, int $host): string
    {
        $octets = self::DOCUMENTATION_NETWORKS[$network] ?? null;

        if ($octets === null || $host < 1 || $host > 254) {
            throw new InvalidArgumentException('La red o el host de demostración no es válido.');
        }

        return implode('.', [...$octets, $host]);
    }
}
