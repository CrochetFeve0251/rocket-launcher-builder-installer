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
            return;
        }

        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json || ! array_key_exists('require-dev', $json)) {
            return;
        }
        $required = $json['require-dev'];

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

            $this->interactor->info("$package: Successfully installed provider successful\n");

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

    protected function get_library_configurations(string $name) {
        $composer_file = "vendor/$name/" . self::PROJECT_FILE;
        if(! $this->filesystem->has($composer_file)) {
            return [];
        }
        $content = $this->filesystem->read($composer_file);
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

        if ( ! $this->filesystem->has( self::BUILDER_FILE ) ) {
            return true;
        }

        $content = $this->filesystem->read( self::BUILDER_FILE );

        return preg_match("/\\\\?" . $provider . "::class,?/", $content);
    }

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

    protected function should_clean(array $configs) {
        if( ! key_exists('clean', $configs) ) {
            return false;
        }

        return $configs['clean'];
    }

    protected function clean_up( string $provider, string $package ) {
        $content = $this->filesystem->read(self::BUILDER_FILE);

        $content = preg_replace('/\n *\\\\' . preg_quote($provider) . '::class,\n/', '', $content);

        $this->filesystem->update(self::BUILDER_FILE, $content);

        $json = json_decode($content, true);

        if(key_exists('require-dev', $json) && key_exists($package, $json['require-dev'])) {
            unset($json['require-dev'][$package]);
        }

        $content = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";

        $this->filesystem->update(self::PROJECT_FILE, $content);
    }

    protected function get_libraries(array $configs) {
        if( ! key_exists('libraries', $configs) ) {
            return [];
        }

        return $configs['libraries'];
    }

    protected function install_library(string $library, string $version) {
        if( ! $this->filesystem->has(self::PROJECT_FILE)) {
            return false;
        }

        $content = $this->filesystem->read(self::PROJECT_FILE);
        $json = json_decode($content,true);
        if(! $json || ! array_key_exists('require-dev', $json) || ! array_key_exists('extra', $json) || ! array_key_exists('mozart', $json['extra']) || ! array_key_exists('packages', $json['extra']['mozart'])) {
            return false;
        }

        if(! key_exists($library, $json['require-dev'])) {
            $json['require-dev'][$library] = $version;
        }

        if(! in_array($library, $json['extra']['mozart']['packages'])) {
            $json['extra']['mozart']['packages'][] = $library;
        }

        $content = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
        $this->filesystem->update(self::PROJECT_FILE, $content);

        return true;
    }
}
