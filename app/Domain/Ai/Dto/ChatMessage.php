<?php

namespace App\Domain\Ai\Dto;

class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
    ) {}

    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    public static function system(string $content): self
    {
        return new self('system', $content);
    }
}
