<?php

namespace Collegeman\Ralph\Prd;

class Prd
{
    /** @param UserStory[] $userStories */
    public function __construct(
        public string $project,
        public string $branchName,
        public string $description,
        public array $userStories = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $stories = array_map(
            fn (array $s) => UserStory::fromArray($s),
            $data['userStories'] ?? []
        );

        return new self(
            project: $data['project'] ?? '',
            branchName: $data['branchName'] ?? '',
            description: $data['description'] ?? '',
            userStories: $stories,
        );
    }

    public function toArray(): array
    {
        return [
            'project' => $this->project,
            'branchName' => $this->branchName,
            'description' => $this->description,
            'userStories' => array_map(fn (UserStory $s) => $s->toArray(), $this->userStories),
        ];
    }

    public function nextStory(): ?UserStory
    {
        $incomplete = array_filter($this->userStories, fn (UserStory $s) => ! $s->passes);

        if (empty($incomplete)) {
            return null;
        }

        usort($incomplete, fn (UserStory $a, UserStory $b) => $a->priority <=> $b->priority);

        return $incomplete[0];
    }

    public function findStory(string $id): ?UserStory
    {
        foreach ($this->userStories as $story) {
            if ($story->id === $id) {
                return $story;
            }
        }

        return null;
    }

    public function findByIssueNumber(int $issueNumber): ?UserStory
    {
        foreach ($this->userStories as $story) {
            if ($story->issueNumber === $issueNumber) {
                return $story;
            }
        }

        return null;
    }

    public function allComplete(): bool
    {
        if (empty($this->userStories)) {
            return false;
        }

        return collect($this->userStories)->every(fn (UserStory $s) => $s->passes);
    }

    public function completedCount(): int
    {
        return collect($this->userStories)->filter(fn (UserStory $s) => $s->passes)->count();
    }

    public function totalCount(): int
    {
        return count($this->userStories);
    }
}
