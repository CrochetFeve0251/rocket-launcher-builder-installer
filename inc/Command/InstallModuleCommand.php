<?php

namespace RocketLauncherBuilderInstaller\Command;

use RocketLauncherBuilder\Commands\Command;
use RocketLauncherBuilderInstaller\Services\ProjectManager;

class InstallModuleCommand extends Command
{
    /**
     * @var ProjectManager
     */
    protected $project_manager;

    public function __construct(ProjectManager $project_manager)
    {
        parent::__construct('initialize', 'Initialize the project');

        $this->project_manager = $project_manager;

        $this

            // Usage examples:
            ->usage(
            // append details or explanation of given example with ` ## ` so they will be uniformly aligned when shown
                '<bold>  $0 auto-install</end> ## Auto install <eol/>'
            );
    }

    public function execute() {
        $this->project_manager->install();
    }
}
