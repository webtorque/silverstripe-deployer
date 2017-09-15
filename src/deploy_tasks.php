<?php
namespace Deployer;

use Deployer\Task\Context;
use \WebTorque\Deployment\Environment;

task(
	'deploy:composer',
	function () {
		$webDir = get('web_dir', false);

		cd("{{release_path}}/{$webDir}");
		run("composer install --no-dev --verbose --prefer-dist --optimize-autoloader --no-progress --no-scripts");
	}
)->desc('Installing composer dependencies');


task(
	'deploy:environment',
	function () {
		$releasePath = Context::get()->getEnvironment()->parse('{{deploy_path}}/current');
		$webDir = get('web_dir', false);

		$tmp = '/tmp/env.php';
		$remoteEnv = Context::get()->getEnvironment()->parse("{{deploy_path}}/shared/_ss_environment.php");

		if (!fileExists($remoteEnv)) {
		    $config = Environment::collectConfig();

			$file = Environment::generateFromConfig("{$releasePath}/{$webDir}", $config);

			file_put_contents($tmp, $file);
			upload($tmp, $remoteEnv);
			unlink($tmp);
		}
	}
)->desc('Setting up environment file');

task(
	'deploy:ssbuild',
	function() {
		$webDir = get('web_dir');
        $httpUser = get('http_user');

		run("cd {{release_path}} && sudo -u {$httpUser} php {$webDir}/framework/cli-script.php dev/build flush=1");
	}
)->desc('Running dev/build on remote server');

task(
    'deploy:submodules',
    function() {
        run("cd {{release_path}} && git submodule update --init --recursive --depth=1");
    }
)->desc('Update submodules');

task(
    'deploy:apache',
    [
        'apache:setup',
        'apache:ensite'
    ]
)->desc('Checking and setting up Apache');

task(
    'deploy:sync',
    [
        'sync:remote',
        'db:permissions'
    ]
)->desc('Uploading from local and setting up database permissions');

task(
    'deploy:writablepermissions',
    function() {
        $dirs = get('writable_dirs');

        if (!empty($dirs)) {
            foreach ($dirs as $dir) {
                sudo("chmod 774 {{release_path}}/{$dir}");
            }
        }
    }
)->desc('Set permissions on writable directories');

task(
    'deploy:cleanupcachedir',
    function() {
        $currentRelease = basename(Context::get()->getEnvironment()->parse('{{release_path}}'));
        echo $currentRelease;
    }
)->desc('Cleanup old SilverStripe cache directories');