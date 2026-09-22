<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArchitectureBaselineTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function the_architecture_deliverables_and_decision_records_exist(): void
    {
        foreach ([
            'docs/ARCHITECTURE.md',
            'docs/DATABASE_ROADMAP.md',
            'docs/adr/0001-laravel-12-php-82.md',
            'docs/adr/0002-synchronous-shared-hosting-runtime.md',
        ] as $path) {
            $this->assertFileExists($this->projectRoot.DIRECTORY_SEPARATOR.$path);
        }
    }

    #[Test]
    public function all_srs_modules_have_stable_architecture_keys(): void
    {
        $architecture = $this->read('docs/ARCHITECTURE.md');
        $roadmap = $this->read('docs/DATABASE_ROADMAP.md');

        preg_match_all('/^\| M\d{2} [^|]+ \| ([a-z]+) \|/m', $architecture, $matches);
        preg_match_all('/^\| M\d{2} ([a-z]+) \|/m', $roadmap, $roadmapMatches);

        $expectedKeys = [
            'dashboard',
            'users',
            'events',
            'clients',
            'booking',
            'venue',
            'vendors',
            'staff',
            'guests',
            'ticketing',
            'registration',
            'tasks',
            'budget',
            'payments',
            'invoices',
            'inventory',
            'catering',
            'decoration',
            'transportation',
            'accommodation',
            'marketing',
            'communications',
            'calendar',
            'documents',
            'reports',
            'analytics',
            'notifications',
            'settings',
            'audit',
        ];

        $this->assertSame($expectedKeys, $matches[1]);
        $this->assertSame($expectedKeys, $roadmapMatches[1]);
    }

    #[Test]
    public function event_scoped_keys_are_fixed_by_the_architecture_contract(): void
    {
        $architecture = $this->read('docs/ARCHITECTURE.md');

        foreach ([
            'venue',
            'vendors',
            'staff',
            'tasks',
            'guests',
            'registration',
            'ticketing',
            'budget',
            'payments',
            'invoices',
            'inventory',
            'catering',
            'decoration',
            'transportation',
            'accommodation',
            'marketing',
            'documents',
            'communications',
        ] as $moduleKey) {
            $this->assertMatchesRegularExpression(
                '/\| M\d{2} [^|]+ \| '.$moduleKey.' \| [^|]+ \| Yes \|/',
                $architecture,
            );
        }
    }

    #[Test]
    public function the_database_roadmap_preserves_core_nullable_and_history_invariants(): void
    {
        $roadmap = $this->read('docs/DATABASE_ROADMAP.md');

        foreach ([
            'events.booking_id is always nullable',
            'clients.user_id, vendors.user_id, and staff_profiles.user_id are nullable',
            'branch_id is nullable unless branch mode is enabled',
            'Every record containing Event-specific operational or financial data has a non-null event_id',
            'Monetary amounts use DECIMAL(19,4); never FLOAT or DOUBLE',
            'actor_user_id',
            'changed_at in UTC',
            'all Event Management business tables remain planned',
        ] as $invariant) {
            $this->assertStringContainsString($invariant, $roadmap);
        }
    }

    #[Test]
    public function composer_keeps_the_approved_runtime_without_forbidden_infrastructure_packages(): void
    {
        $composer = json_decode($this->read('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $packages = array_merge(
            array_keys($composer['require']),
            array_keys($composer['require-dev']),
        );

        $this->assertSame('^8.2', $composer['require']['php']);
        $this->assertSame('^12.0', $composer['require']['laravel/framework']);
        $this->assertNotContains('laravel/horizon', $packages);
        $this->assertNotContains('predis/predis', $packages);
        $this->assertNotContains('beyondcode/laravel-websockets', $packages);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->projectRoot.DIRECTORY_SEPARATOR.$path);

        $this->assertIsString($contents);

        return $contents;
    }
}
