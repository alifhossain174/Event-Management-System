<?php

namespace Tests\Feature\Bootstrap;

use Tests\TestCase;

final class SharedHostingConfigurationTest extends TestCase
{
    public function test_stateful_services_use_shared_hosting_safe_drivers(): void
    {
        $environmentExample = file_get_contents(base_path('.env.example'));

        $this->assertIsString($environmentExample);
        $this->assertStringContainsString('CACHE_STORE=file', $environmentExample);
        $this->assertStringContainsString('SESSION_DRIVER=file', $environmentExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $environmentExample);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $environmentExample);
        $this->assertStringContainsString('APP_TIMEZONE=UTC', $environmentExample);
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('local', config('filesystems.default'));
    }
}
