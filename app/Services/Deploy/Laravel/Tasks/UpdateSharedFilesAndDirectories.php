<?php

namespace App\Services\Deploy\Laravel\Tasks;

use App\Services\Deploy\DeploymentTasks\Task;

class UpdateSharedFilesAndDirectories extends Task
{
    protected $sharedDirectotories = [
        'storage',
    ];

    protected $sharedFiles = [
        '.env'
    ];

    public function run()
    {
        $this->addStatusLog(self::STATUS_RUNNING);

        $currentRelease = trim($this->host->runCommand("cat {$this->deployPath}/.dep/latest_release"),"\n");

        $releasePath = "{$this->deployPath}/releases/{$currentRelease}";
        $sharedPath = "{$this->deployPath}/shared/";

        foreach ($this->sharedDirectotories as $sharedDir) {
            $sharedDir = trim($sharedDir,'/');

            if ( !$this->host->test("[ -d {$releasePath}/{$sharedDir} ]") ){
                continue;
            }

            $this->host->runCommand("[ -d {$sharedPath}/{$sharedDir} ] || mkdir {$sharedPath}/{$sharedDir}");

            $this->host->runCommand("rsync -av {$releasePath}/{$sharedDir}/ {$sharedPath}/{$sharedDir}");

            $this->host->runCommand("rm -rf {$releasePath}/{$sharedDir}");

            $this->host->runCommand("ln -s {$sharedPath}/{$sharedDir} {$releasePath}/{$sharedDir}");

        }

        foreach ($this->sharedFiles as $sharedFile) {
            $sharedFile = trim($sharedFile,'/');

            if ( !$this->host->test("[ -f {$releasePath}/{$sharedFile} ]") ){
                continue;
            }

            $this->host->runCommand("[ -f {$sharedPath}/{$sharedFile} ] || touch {$sharedPath}/{$sharedFile}");

            $this->host->runCommand("cp {$releasePath}/{$sharedFile}/ {$sharedPath}/{$sharedFile}");

            $this->host->runCommand("rm -f {$releasePath}/{$sharedFile}");

            $this->host->runCommand("ln -s {$sharedPath}/{$sharedFile} {$releasePath}/{$sharedFile}");

        }

        $this->addStatusLog(self::STATUS_COMPLETED);
    }

    public function getTaskName(): string
    {
        return 'Update Shared Dirs & Files';
    }

    public function getTaskDescription(): string
    {
        return '';
    }
}
