<?php
namespace RocketLauncherBuilderInstaller;

use League\Flysystem\Filesystem;
use RocketLauncherBuilder\App;
use RocketLauncherBuilder\Entities\Configurations;
use RocketLauncherBuilder\ServiceProviders\ServiceProviderInterface;
use RocketLauncherBuilderInstaller\Command\InstallModuleCommand;
use RocketLauncherBuilderInstaller\Services\ProjectManager;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Configuration from the project.
     *
     * @var Configurations
     */
    protected $configs;

    /**
     * Instantiate the class.
     *
     * @param Configurations $configs configuration from the project.
     * @param Filesystem $filesystem Interacts with the filesystem.
     */
    public function __construct(Configurations $configs, Filesystem $filesystem)
    {
        $this->configs = $configs;
        $this->filesystem = $filesystem;
    }

    public function attach_commands(App $app): App
    {
        $project_manager = new ProjectManager($this->configs, $this->filesystem, $app->io());

        $app->add(new InstallModuleCommand($project_manager));

        return $app;
    }
}
