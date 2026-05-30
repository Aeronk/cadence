<?php

namespace App\Services\AI;

interface Provider
{
    /**
     * Send a prompt and receive a free-text completion.
     *
     * @param  array{role:string,content:string}[]  $messages
     */
    public function complete(array $messages, array $options = []): string;
}
