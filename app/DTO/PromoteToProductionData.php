<?php

namespace App\DTO;

readonly class PromoteToProductionData
{
    public function __construct(
        public int $siteId,
        public string $stageDomain,
        public string $prodDomain,
        public string $mode,
        public int $requestedBy,
        public string $confirmPhrase,
    ) {}
}
