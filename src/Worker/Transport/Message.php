<?php


namespace Temporal\Client\Worker\Transport;

final class Message
{
    public ?string $messages;
    public array $context;

    /**
     * @param string $messages
     * @param array $context
     */
    public function __construct(string $messages, array $context)
    {
        $this->messages = $messages;
        $this->context = $context;
    }
}
