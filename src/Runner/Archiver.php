<?php

namespace Collegeman\Ralph\Runner;

use Collegeman\Ralph\Prd\PrdManager;
use Collegeman\Ralph\Progress\ProgressLog;

class Archiver
{
    private string $lastBranchPath;

    private string $archiveDir;

    public function __construct(
        private PrdManager $prdManager,
        private ProgressLog $progressLog,
    ) {
        $this->lastBranchPath = base_path('.ralph-branch');
        $this->archiveDir = base_path('archive');
    }

    public function checkAndArchive(): ?string
    {
        if (! $this->prdManager->exists()) {
            return null;
        }

        $prd = $this->prdManager->load();
        $currentBranch = $prd->branchName;
        $lastBranch = $this->readLastBranch();

        if (! $currentBranch || ! $lastBranch) {
            $this->writeLastBranch($currentBranch);

            return null;
        }

        if ($currentBranch === $lastBranch) {
            return null;
        }

        $archivePath = $this->archive($lastBranch);
        $this->progressLog->clear();
        $this->progressLog->initialize();
        $this->writeLastBranch($currentBranch);

        return $archivePath;
    }

    public function trackBranch(): void
    {
        if (! $this->prdManager->exists()) {
            return;
        }

        $prd = $this->prdManager->load();

        if ($prd->branchName) {
            $this->writeLastBranch($prd->branchName);
        }
    }

    private function archive(string $branchName): string
    {
        $date = date('Y-m-d');
        $folderName = str_replace('ralph/', '', $branchName);
        $folderName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $folderName);
        $archivePath = $this->archiveDir."/{$date}-{$folderName}";

        if (! is_dir($archivePath)) {
            mkdir($archivePath, 0755, true);
        }

        $prdPath = $this->prdManager->getPath();
        $progressPath = $this->progressLog->getPath();

        if (file_exists($prdPath)) {
            copy($prdPath, $archivePath.'/prd.json');
        }

        if (file_exists($progressPath)) {
            copy($progressPath, $archivePath.'/progress.txt');
        }

        return $archivePath;
    }

    private function readLastBranch(): ?string
    {
        if (! file_exists($this->lastBranchPath)) {
            return null;
        }

        $branch = trim(file_get_contents($this->lastBranchPath));

        return $branch ?: null;
    }

    private function writeLastBranch(string $branch): void
    {
        file_put_contents($this->lastBranchPath, $branch);
    }
}
