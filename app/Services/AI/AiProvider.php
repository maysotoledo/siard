<?php

namespace App\Services\AI;

interface AiProvider
{
    public function generate(string $prompt, array $options = []): string;
}
