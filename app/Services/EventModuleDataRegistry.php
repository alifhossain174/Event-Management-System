<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;
use InvalidArgumentException;

final class EventModuleDataRegistry
{
    /** @var array<string, EventModuleDataDetector> */
    private array $detectors = [];

    /** @param list<string> $moduleKeys */
    public function __construct(private readonly array $moduleKeys) {}

    public function register(EventModuleDataDetector $detector): void
    {
        $key = $detector->moduleKey();

        if (! in_array($key, $this->moduleKeys, true)) {
            throw new InvalidArgumentException("Cannot register a data detector for unknown Event module [{$key}].");
        }

        if (isset($this->detectors[$key])) {
            throw new InvalidArgumentException("A data detector is already registered for Event module [{$key}].");
        }

        $this->detectors[$key] = $detector;
    }

    public function hasData(Event $event, string $moduleKey): bool
    {
        $this->guardKnownKey($moduleKey);

        return ($this->detectors[$moduleKey] ?? null)?->hasData($event) ?? false;
    }

    /**
     * @param  iterable<string>  $moduleKeys
     * @return array<string, bool>
     */
    public function presenceFor(Event $event, iterable $moduleKeys): array
    {
        $presence = [];

        foreach ($moduleKeys as $moduleKey) {
            $presence[$moduleKey] = $this->hasData($event, $moduleKey);
        }

        return $presence;
    }

    /** @return list<string> */
    public function registeredKeys(): array
    {
        return array_keys($this->detectors);
    }

    private function guardKnownKey(string $moduleKey): void
    {
        if (! in_array($moduleKey, $this->moduleKeys, true)) {
            throw new InvalidArgumentException("Unknown Event module [{$moduleKey}].");
        }
    }
}
