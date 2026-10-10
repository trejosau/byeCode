<?php

namespace App\Domain\Demo;

final class DemoContext
{
    public bool $loading = false;

    public ?string $runId = null;

    /** @var list<string> */
    public array $files = [];
}
