<?php

namespace RocketLauncherBuilderInstaller\Services;

use Ahc\Cli\Helper\Shell;
use Ahc\Cli\IO\Interactor;
use League\Flysystem\Filesystem;
use RocketLauncherBuilder\Entities\Configurations;

class ProjectManager
{
    /**
     * @var Configurations
     */
    protected $configurations;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Interactor
     */
    protected $interactor;

    const PROJECT_FILE = 'composer.json';
    const BUILDER_FILE = 'bin/generator';

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Configurations $configurations, Filesystem $filesystem, Interactor $interactor)
    {
        $this->configurations = $configurations;
        $this->filesystem = $filesystem;
        $this->interactor = $interactor;
    }

    public function install() {
        if( ! $this->filesystem->has(self::PROJECT_FILE)) {
            return false;
        }

        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json || ! array_key_exists('require-dev', $json)) {
            return false;
        }
        $required = $json['require-dev'];
        $to_install = [];
        foreach ($required as $package => $version) {
            if(! preg_match('/-take-off$/', $package)) {
                continue;
            }

            $configs = $this->get_library_configurations( $package );

            if( ! $configs ) {
                continue;
            }

            $provider = $this->get_provider($configs);

            if(! $provider || $this->has_provider_installed($provider) ) {
                continue;
            }

            $this->install_provider($provider);
            $this->interactor->info("Successfully installed $package provider successful");

            $command = $this->get_command($configs);

            if( ! $command ) {
                continue;
            }

            if(! $this->should_auto_install($configs) ) {
                $this->interactor->info("Please run '$command' to finish the installation");
                continue;
            }

            $this->auto_install($command);
            $this->interactor->info("Take off from $package successful");
        }

    }

    protected function get_library_configurations(string $name) {
        $composer_file = "vendor/$name/" . self::PROJECT_FILE;
        if(! $this->filesystem->has($composer_file)) {
            return [];
        }
        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json || ! key_exists('extra', $json) || ! key_exists('rocket-launcher', $json['extra'])) {
            return [];
        }
        return $json['extra']['rocket-launcher'];
    }

    protected function get_provider(array $configs) {
        if( ! key_exists('provider', $configs) ) {
            return '';
        }

        return $configs['provider'];
    }

    protected function has_provider_installed(string $provider){
        $base_namespace = $this->configurations->getBaseNamespace();
        $providers_path = 'configs/providers.php';

        if ( ! $this->filesystem->has( $providers_path ) ) {
            return true;
        }

        $content = $this->filesystem->read( $providers_path );

        return preg_match("/\\\\?$base_namespace\\\\Dependencies\\\\" . $provider . "::class,?/", $content);
    }

    protected function install_provider(string $provider) {
        $base_namespace = $this->configurations->getBaseNamespace();
        $providers_path = 'configs/providers.php';

        if ( ! $this->filesystem->has( $providers_path ) ) {
            return;
        }

        $content = $this->filesystem->read( $providers_path );

        if(! preg_match('/(?<array>return\s\[(?<content>(?:[^[\]]+|(?R))*)\]\s*;\s*$)/', $content, $results)) {
            return;
        }

        $result_content = $results['content'];
        $result_content = "\n    \\$base_namespace\\Dependencies\\" . $provider . "::class," . $result_content;
        $content = str_replace($results['content'], $result_content, $content);

        $this->filesystem->update($providers_path, $content);
    }

    protected function get_command(array $configs) {
        if( ! key_exists('command', $configs) ) {
            return '';
        }

        return $configs['command'];
    }

    protected function should_auto_install(array $configs) {
        if( ! key_exists('install', $configs) ) {
            return '';
        }

        return $configs['install'];
    }

    protected function auto_install(string $command ) {
        $shell = new Shell("{$this->filesystem->getAdapter()->getPathPrefix()}/bin/generator $command");

        $shell->execute();
    }
}
