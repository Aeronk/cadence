<?php

namespace App\Services\AI;

class FakeProvider implements Provider
{
    /** @var string[] */
    public array $queued = [];

    /** @var array<int,array{messages:array,options:array}> */
    public array $calls = [];

    public function queue(string $response): void
    {
        $this->queued[] = $response;
    }

    public function complete(array $messages, array $options = []): string
    {
        $this->calls[] = ['messages' => $messages, 'options' => $options];

        if ($this->queued !== []) {
            return array_shift($this->queued);
        }

        $last = end($messages);
        return "FAKE: " . (is_array($last) ? ($last['content'] ?? '') : '');
    }
}
