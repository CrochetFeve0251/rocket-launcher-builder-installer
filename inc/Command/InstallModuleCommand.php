<?php

namespace LaunchpadCLIInstaller\Command;

use LaunchpadCLI\Commands\Command;
use LaunchpadCLIInstaller\Services\ProjectManager;

class InstallModuleCommand extends Command
{
    /**
     * Handle project operations.
     *
     * @var ProjectManager
     */
    protected $project_manager;

    /**
     * Instantiate the class.
     *
     * @param ProjectManager $project_manager Handle project operations.
     */
    public function __construct(ProjectManager $project_manager)
    {
        parent::__construct('auto-install', 'Auto install modules');

        $this->project_manager = $project_manager;

        $this

            // Usage examples:
            ->usage(
            // append details or explanation of given example with ` ## ` so they will be uniformly aligned when shown
                '<bold>  $0 auto-install</end> ## Auto install modules<eol/>'
            );
    }

    /**
     * Execute the command.
     *
     * @return void
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function execute() {
        $this->project_manager->install();
    }
}
