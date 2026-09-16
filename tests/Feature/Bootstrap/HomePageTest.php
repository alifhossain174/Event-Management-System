<?php

namespace Tests\Feature\Bootstrap;

use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_the_bootstrap_landing_page_is_available(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Event Management System')
            ->assertSee('Laravel 12 bootstrap is ready');
    }
}
