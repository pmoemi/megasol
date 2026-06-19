<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\System\CronHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_status_is_unknown_without_heartbeat(): void
    {
        $status = app(CronHealthService::class)->status();

        $this->assertSame('unknown', $status['state']);
        $this->assertFalse($status['is_healthy']);
        $this->assertSame('Never', $status['last_run_human']);
    }

    public function test_scheduler_status_is_ok_when_heartbeat_is_recent(): void
    {
        Setting::set(CronHealthService::KEY_SCHEDULER_HEARTBEAT, now()->subMinute()->toIso8601String());

        $status = app(CronHealthService::class)->status();

        $this->assertSame('ok', $status['state']);
        $this->assertTrue($status['is_healthy']);
    }

    public function test_scheduler_status_is_error_when_heartbeat_is_stale(): void
    {
        Setting::set(CronHealthService::KEY_SCHEDULER_HEARTBEAT, now()->subHours(2)->toIso8601String());

        $status = app(CronHealthService::class)->status();

        $this->assertSame('error', $status['state']);
        $this->assertFalse($status['is_healthy']);
    }

    public function test_record_automations_run_updates_task_status(): void
    {
        app(CronHealthService::class)->recordAutomationsRun();

        $status = app(CronHealthService::class)->status();
        $automations = collect($status['tasks'])->firstWhere('key', 'automations');

        $this->assertSame('ok', $automations['state']);
        $this->assertNotSame('Never', $automations['last_run_human']);
    }
}
