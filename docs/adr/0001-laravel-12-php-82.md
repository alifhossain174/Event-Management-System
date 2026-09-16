# ADR 0001 Laravel 12 on PHP 8.2

## Status

Accepted on 16 September 2026.

## Context

The verified XAMPP runtime is PHP 8.2.12. The application must run locally and on a compatible cPanel host without a machine-wide runtime change. Laravel 12 supports PHP 8.2, while Laravel 13 requires PHP 8.3.

The product requires server-rendered forms, authorization, relational persistence, scheduled commands, protected files, reporting, and conventional shared-hosting deployment. These needs are covered by Laravel 12 and do not justify a different framework or a distributed architecture.

## Decision

Use Laravel 12 with Composer constraint ^12.0 on PHP ^8.2. Build one Laravel MVC modular monolith with:

- MySQL-compatible InnoDB and utf8mb4;
- Blade, Bootstrap 5, and vanilla JavaScript;
- standard Laravel controllers, Form Requests, Policies/Gates, services, Eloquent models, migrations, factories, seeders, events/listeners, scheduler, filesystem, logging, and tests;
- domain-oriented subfolders inside normal Laravel conventions;
- no third-party modular framework.

Laravel 13 is not adopted until both local and production environments deliberately move to PHP 8.3 or newer and the application passes an upgrade review.

## Consequences

- Development and cPanel deployment use the same supported PHP baseline.
- Framework security support dates must be monitored and upgrades planned before end of support.
- Packages may be added only when Composer resolves a maintained PHP 8.2 and Laravel 12 compatible release and Laravel itself is insufficient.
- Domain boundaries are enforced by namespaces, service ownership, tests, and review rather than separate deployments or package machinery.
- A future framework upgrade is a planned compatibility change, not an incidental Composer update.

## Revisit triggers

- Production hosting no longer supports a secure Laravel 12/PHP 8.2 combination.
- Laravel 12 approaches end of security support.
- A required maintained package drops PHP 8.2 or Laravel 12 support.
- The approved production runtime moves to PHP 8.3 or newer and an upgrade is scheduled.
