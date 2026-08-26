<?php

namespace Tests;

use App\Services\UrlSafetyService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UrlSafetyServiceTest extends TestCase
{
    /** @dataProvider unsafeUrlProvider */
    public function testUnsafeDestinationsAreRejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UrlSafetyService())->assertSafeUrl($url);
    }

    public function testAllowedPublicHostIsAccepted(): void
    {
        $url = 'https://8.8.8.8/news';
        self::assertSame($url, (new UrlSafetyService())->assertSafeUrl($url, ['8.8.8.8']));
    }

    public function testUnexpectedPublicHostIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UrlSafetyService())->assertSafeUrl('https://8.8.8.8/news', ['example.com']);
    }

    public function unsafeUrlProvider(): array
    {
        return [
            'unsupported scheme' => ['file:///etc/passwd'],
            'localhost' => ['http://localhost/admin'],
            'loopback IPv4' => ['http://127.0.0.1/admin'],
            'private IPv4' => ['http://10.0.0.1/metadata'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data'],
            'loopback IPv6' => ['http://[::1]/admin'],
            'embedded credentials' => ['https://user:password@8.8.8.8/news'],
        ];
    }
}
