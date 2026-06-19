<?php

namespace Tests\Unit;

use App\Services\Integrations\PayGroService;
use ReflectionMethod;
use Tests\TestCase;

class PayGroPaymentPlanNameTest extends TestCase
{
    public function test_core_name_strips_parenthetical_suffixes(): void
    {
        $this->assertSame(
            'kosap mega 20',
            $this->coreName('KOSAP MEGA 20 (P.P.U)'),
        );

        $this->assertSame(
            'mega 20',
            $this->coreName('Mega 20 (24 months)'),
        );
    }

    public function test_fuzzy_match_links_sale_plan_to_master_plan(): void
    {
        $index = [
            'kosap mega 20' => [
                ['InstallmentNumber' => 1, 'InstallmentAmount' => 500],
                ['InstallmentNumber' => 2, 'InstallmentAmount' => 500],
            ],
        ];

        $rows = $this->findRows('KOSAP MEGA 20 (P.P.U)', $index);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['InstallmentNumber']);
    }

    public function test_fuzzy_match_links_variant_plan_names(): void
    {
        $index = [
            'kosap mega 20' => [
                ['InstallmentNumber' => 1],
            ],
        ];

        $this->assertNotEmpty($this->findRows('Mega 20 (24 months)', $index));
    }

    public function test_fuzzy_match_does_not_cross_unrelated_plans(): void
    {
        $index = [
            'kosap mega 20' => [
                ['InstallmentNumber' => 1],
            ],
            'kosap mega 50' => [
                ['InstallmentNumber' => 1],
            ],
        ];

        $rows = $this->findRows('Jixel Mega 50+ 24 TV', $index);

        $this->assertSame([], $rows);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $index
     * @return array<int, array<string, mixed>>
     */
    protected function findRows(string $planName, array $index): array
    {
        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'findPaymentPlanRowsForName');

        return $method->invoke($service, $planName, $index);
    }

    protected function coreName(string $planName): string
    {
        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'paymentPlanCoreName');

        return $method->invoke($service, $planName);
    }
}
