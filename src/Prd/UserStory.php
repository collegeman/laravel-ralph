<?php

namespace Snarktank\Ralph\Prd;

class UserStory
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public array $acceptanceCriteria,
        public int $priority,
        public bool $passes = false,
        public string $notes = '',
        public ?int $issueNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'],
            acceptanceCriteria: $data['acceptanceCriteria'] ?? [],
            priority: $data['priority'],
            passes: $data['passes'] ?? false,
            notes: $data['notes'] ?? '',
            issueNumber: $data['issueNumber'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'acceptanceCriteria' => $this->acceptanceCriteria,
            'priority' => $this->priority,
            'passes' => $this->passes,
            'notes' => $this->notes,
            'issueNumber' => $this->issueNumber,
        ], fn ($value) => $value !== null);
    }
}
