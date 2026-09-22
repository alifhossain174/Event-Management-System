<?php

namespace Tests\Feature\Bootstrap;

use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_the_framework_health_route_is_available(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
