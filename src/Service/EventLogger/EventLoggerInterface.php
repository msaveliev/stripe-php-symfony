<?php

declare(strict_types=1);

namespace App\Service\EventLogger;

interface EventLoggerInterface
{
    public function log(string $uniqueKey): void;

    public function isLogged(string $uniqueKey): bool;
}
