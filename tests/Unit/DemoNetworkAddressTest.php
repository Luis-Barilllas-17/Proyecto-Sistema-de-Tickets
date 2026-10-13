<?php

namespace Tests\Unit;

use Database\Seeders\Support\DemoNetworkAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DemoNetworkAddressTest extends TestCase
{
    #[DataProvider('documentationNetworks')]
    public function test_it_builds_valid_addresses_only_from_documentation_networks(
        string $network,
        array $expectedNetworkOctets,
    ): void {
        $address = DemoNetworkAddress::ipv4($network, 41);

        $this->assertNotFalse(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
        $this->assertSame([...$expectedNetworkOctets, 41], array_map('intval', explode('.', $address)));
    }

    #[DataProvider('invalidAddresses')]
    public function test_it_rejects_unknown_networks_and_invalid_host_numbers(string $network, int $host): void
    {
        $this->expectException(InvalidArgumentException::class);

        DemoNetworkAddress::ipv4($network, $host);
    }

    public static function documentationNetworks(): array
    {
        return [
            'operations' => [DemoNetworkAddress::OPERATIONS, [192, 0, 2]],
            'infrastructure' => [DemoNetworkAddress::INFRASTRUCTURE, [198, 51, 100]],
            'development' => [DemoNetworkAddress::DEVELOPMENT, [203, 0, 113]],
        ];
    }

    public static function invalidAddresses(): array
    {
        return [
            'unknown network' => ['institutional-network', 41],
            'network address' => [DemoNetworkAddress::OPERATIONS, 0],
            'broadcast address' => [DemoNetworkAddress::OPERATIONS, 255],
        ];
    }
}
