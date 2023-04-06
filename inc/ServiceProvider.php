<?php
namespace LaunchpadCLIInstaller;

use League\Flysystem\Filesystem;
use LaunchpadCLI\App;
use LaunchpadCLI\Entities\Configurations;
use LaunchpadCLI\ServiceProviders\ServiceProviderInterface;
use LaunchpadCLIInstaller\Command\InstallModuleCommand;
use LaunchpadCLIInstaller\Services\ProjectManager;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Interacts with the filesystem.
     *
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

    /**
     * Attach library commands to the application.
     *
     * @param App $app Application.
     *
     * @return App
     */
    public function attach_commands(App $app): App
    {
        $project_manager = new ProjectManager($this->configs, $this->filesystem, $app->io());

        $app->add(new InstallModuleCommand($project_manager));

        return $app;
    }
}
