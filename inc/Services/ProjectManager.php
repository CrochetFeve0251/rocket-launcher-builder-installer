<?php

namespace LaunchpadCLIInstaller\Services;

use Ahc\Cli\Helper\Shell;
use Ahc\Cli\IO\Interactor;
use League\Flysystem\Filesystem;
use LaunchpadCLI\Entities\Configurations;

class ProjectManager
{
    /**
     * Configuration from the project.
     *
     * @var Configurations
     */
    protected $configurations;

    /**
     * Interacts with the filesystem.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Interacts with the user.
     *
     * @var Interactor
     */
    protected $interactor;

    const PROJECT_FILE = 'composer.json';
    const BUILDER_FILE = 'bin/generator';

    /**
     * Instantiate the class.
     *
     * @param Configurations $configurations Configuration from the project.
     * @param Filesystem $filesystem Interacts with the filesystem.
     * @param Interactor $interactor Interacts with the user.
     */
    public function __construct(Configurations $configurations, Filesystem $filesystem, Interactor $interactor)
    {
        $this->configurations = $configurations;
        $this->filesystem = $filesystem;
        $this->interactor = $interactor;
    }

    /**
     * Install libraries.
     *
     * @return void
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function install() {
        if( ! $this->filesystem->has(self::PROJECT_FILE)) {
            return;
        }

        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json) {
            return;
        }

        $required = [];

        if(array_key_exists('require-dev', $json)) {
            $required = array_merge($required, $json['require-dev']);
        }

        if(array_key_exists('require', $json)) {
            $required = array_merge($required, $json['require']);
        }

        foreach ($required as $package => $version) {
            $configs = $this->get_library_configurations( $package );

            if( ! $configs ) {
                continue;
            }

            $provider = $this->get_provider($configs);

            if( $provider && ! $this->has_provider_installed($provider) ) {
                $this->install_provider($provider);

                $this->interactor->info("$package: Successfully installed provider successful\n");
            }

            $libraries = $this->get_libraries($configs);

            foreach ($libraries as $library => $library_version) {
                $this->install_library($library, $library_version);
            }

            $this->handle_command($configs, $package);

            if(! $this->should_clean($configs)) {
                continue;
            }

            $this->clean_up($provider, $package);

            $this->interactor->info("$package: Installation package cleaned\n");
        }
    }

    /**
     * Get library configurations.
     *
     * @param string $name library name.
     *
     * @return array|mixed
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function get_library_configurations(string $name) {
        $composer_file = "vendor/$name/" . self::PROJECT_FILE;
        if(! $this->filesystem->has($composer_file)) {
            return [];
        }
        $content = $this->filesystem->read($composer_file);
        $json = json_decode($content,true);
        if(! $json || ! key_exists('extra', $json) || ! key_exists('launchpad', $json['extra'])) {
            return [];
        }
        return $json['extra']['launchpad'];
    }

    /**
     * Get provider from configurations.
     *
     * @param array $configs configurations from the library.
     *
     * @return string
     */
    protected function get_provider(array $configs) {
        if( ! key_exists('provider', $configs) ) {
            return '';
        }

        return $configs['provider'];
    }

    /**
     * IS the provider installed.
     *
     * @param string $provider provider name.
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function has_provider_installed(string $provider){

        if ( ! $this->filesystem->has( self::BUILDER_FILE ) ) {
            return true;
        }

        $content = $this->filesystem->read( self::BUILDER_FILE );

        return (bool) preg_match("/" . str_replace('\\', '\\\\', $provider) . "::class,?/", $content);
    }

    /**
     * Install the provider.
     *
     * @param string $provider provider name.
     * @return void
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function install_provider(string $provider) {

        if ( ! $this->filesystem->has( self::BUILDER_FILE ) ) {
            return;
        }

        $content = $this->filesystem->read( self::BUILDER_FILE );

        if(! preg_match('/AppBuilder::init\(__DIR__ . \'\/..\/\',\s*(\[(?<content>[^\]]*))?/', $content, $results)) {

            return;
        }

        if(key_exists('content', $results)) {
            $result_content = $results['content'];
            $result_content = "\n    \\" . $provider . "::class," . $result_content;
            $content = str_replace($results['content'], $result_content, $content);

            $this->filesystem->update(self::BUILDER_FILE, $content);
            return;
        }

        $result_content = $results[0] . ", [\n    \\" . $provider . "::class,\n]";
        $content = str_replace($results['content'], $result_content, $content);

        $this->filesystem->update(self::BUILDER_FILE, $content);
    }

    protected function is_already_installed(string $provider) {
        if ( ! $this->filesystem->has( self::BUILDER_FILE ) ) {
            return;
        }

        $content = $this->filesystem->read( self::BUILDER_FILE );

        return preg_match("/$provider::class/", $content);
    }

    /**
     * Handle the package command.
     *
     * @param array $configs configurations from the library.
     * @param string $package provider name.
     * @return void
     */
    protected function handle_command(array $configs, string $package) {
        $command = $this->get_command($configs);

        if( ! $command ) {
            return;
        }

        if(! $this->should_auto_install($configs) ) {
            $this->interactor->info("$package: Please run '$command' to finish the installation\n");
            return;
        }

        $this->auto_install($command);
        $this->interactor->info("$package: Take off successful\n");
    }

    /**
     * Get the library command.
     *
     * @param array $configs configurations from the library.
     *
     * @return mixed|string
     */
    protected function get_command(array $configs) {
        if( ! key_exists('command', $configs) ) {
            return '';
        }

        return $configs['command'];
    }

    /**
     * Should the library auto install.
     *
     * @param array $configs configurations from the library.
     *
     * @return mixed|string
     */
    protected function should_auto_install(array $configs) {
        if( ! key_exists('install', $configs) ) {
            return '';
        }

        return $configs['install'];
    }

    /**
     * Auto install the library.
     *
     * @param string $command library command.
     * @return void
     */
    protected function auto_install(string $command ) {
        $shell = new Shell("{$this->filesystem->getAdapter()->getPathPrefix()}/bin/generator $command");

        $shell->execute();
    }

    /**
     * Should the library be cleaned.
     *
     * @param array $configs configurations from the library.
     *
     * @return boolean
     */
    protected function should_clean(array $configs) {
        if( ! key_exists('clean', $configs) ) {
            return false;
        }

        return $configs['clean'];
    }

    /**
     * Clean up the library.
     *
     * @param string $provider provider name.
     *
     * @param string $package provider name.
     *
     * @return void
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function clean_up( string $provider, string $package ) {
        $content = $this->filesystem->read(self::BUILDER_FILE);

        $content = preg_replace('/ *\\\\?' . str_replace('\\', '\\\\', $provider) . '::class,?\n/', '', $content);

        $this->filesystem->update(self::BUILDER_FILE, $content);

        $content = $this->filesystem->read(self::PROJECT_FILE);

        $json = json_decode($content, true);

        if(!$json) {
            return;
        }

        if(key_exists('require-dev', $json) && key_exists($package, $json['require-dev'])) {
            unset($json['require-dev'][$package]);
        }

        if(key_exists('require', $json) && key_exists($package, $json['require'])) {
            unset($json['require'][$package]);
        }

        $content = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";

        $this->filesystem->update(self::PROJECT_FILE, $content);
    }

    /**
     * Get libraries to install.
     *
     * @param array $configs configurations from the library.
     *
     * @return array|mixed
     */
    protected function get_libraries(array $configs) {
        if( ! key_exists('libraries', $configs) ) {
            return [];
        }

        return $configs['libraries'];
    }

    /**
     * Install the library.
     *
     * @param string $library library name.
     *
     * @param string $version library version.
     *
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function install_library(string $library, string $version) {
        if( ! $this->filesystem->has(self::PROJECT_FILE)) {
            return false;
        }

        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json || ! array_key_exists('require-dev', $json) || ! array_key_exists('extra', $json) ) {
            return false;
        }

        if(! key_exists($library, $json['require-dev'])) {
            $json['require-dev'][$library] = $version;
        }

        if( array_key_exists('mozart', $json['extra']) && array_key_exists('packages', $json['extra']['mozart']) && ! in_array($library, $json['extra']['mozart']['packages'])) {
            $json['extra']['mozart']['packages'][] = $library;
        }

        if( array_key_exists('strauss', $json['extra']) && array_key_exists('packages', $json['extra']['strauss']) && ! in_array($library, $json['extra']['strauss']['packages'])) {
            $json['extra']['strauss']['packages'][] = $library;
        }

        $content = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
        $this->filesystem->update(self::PROJECT_FILE, $content);

        return true;
    }
}
