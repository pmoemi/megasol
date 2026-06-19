<?php

namespace Tests\Unit;

use App\Services\Integrations\PayGroService;
use ReflectionMethod;
use Tests\TestCase;

class PayGroDateParseTest extends TestCase
{
    public function test_slash_dates_use_day_first_format(): void
    {
        $this->assertSame('2025-02-07', $this->parseDate('07/02/2025')?->format('Y-m-d'));
        $this->assertSame('2024-10-30', $this->parseDate('30/10/2024')?->format('Y-m-d'));
        $this->assertSame('2025-03-26', $this->parseDate('26/03/2025')?->format('Y-m-d'));
    }

    public function test_iso_dates_still_parse(): void
    {
        $this->assertSame(
            '2026-06-09',
            $this->parseDateTime('2026-06-09T19:31:03')?->format('Y-m-d'),
        );
    }

    public function test_sentinel_dates_return_null(): void
    {
        $this->assertNull($this->parseDate('0001-01-01T00:00:00'));
    }

    protected function parseDate(string $value): ?\Carbon\Carbon
    {
        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'parsePayGroDate');

        return $method->invoke($service, $value);
    }

    protected function parseDateTime(string $value): ?\Carbon\Carbon
    {
        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'parsePayGroDateTime');

        return $method->invoke($service, $value);
    }
}
