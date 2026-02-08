<?php

namespace Collegeman\Ralph\Prd;

use RuntimeException;

class PrdManager
{
    public function __construct(
        private string $path,
    ) {}

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function load(): Prd
    {
        if (! $this->exists()) {
            throw new RuntimeException("PRD file not found at: {$this->path}");
        }

        $json = file_get_contents($this->path);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return Prd::fromArray($data);
    }

    public function save(Prd $prd): void
    {
        $json = json_encode($prd->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->path, $json."\n");
    }

    public function saveRaw(string $json): void
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $prd = Prd::fromArray($decoded);
        $this->save($prd);
    }

    public function nextStory(): ?UserStory
    {
        return $this->load()->nextStory();
    }

    public function markPassing(string $storyId): void
    {
        $prd = $this->load();
        $story = $prd->findStory($storyId);

        if (! $story) {
            throw new RuntimeException("Story not found: {$storyId}");
        }

        $story->passes = true;
        $this->save($prd);
    }

    public function allComplete(): bool
    {
        return $this->load()->allComplete();
    }

    public function resetStories(): void
    {
        $prd = $this->load();

        foreach ($prd->userStories as $story) {
            $story->passes = false;
        }

        $this->save($prd);
    }

    public function addStory(UserStory $story): void
    {
        $prd = $this->load();
        $prd->userStories[] = $story;
        $this->save($prd);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
