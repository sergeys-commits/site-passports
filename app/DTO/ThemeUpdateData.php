<?php

namespace App\DTO;

readonly class ThemeUpdateData
{
    public function __construct(
        public int $siteId,
        public string $targetVersion,
        public string $environment,
        public string $mode,
        public int $requestedBy,
    ) {}
}
