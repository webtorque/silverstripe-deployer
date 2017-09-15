<?php
namespace Deployer;

task(
	'apache:setup',
	function () {
        $env = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();

        if ($version = apacheVersion()) {
            if (!apacheSetup($env['domain'])) {
                //apache 2.4
                if (($version['major'] === 2 && $version['minor'] === 4) || $version['minor'] === 2) {
                    //get config
                    $config = file_get_contents(
                        "deployer/templates/apache.{$version['major']}.{$version['minor']}.conf"
                    );

                    //get some user input
                    $aliases = [];
                    while ($alias = ask('Would you like to add a domain alias (leave blank to continue)', '')) {
                        $aliases[] = 'ServerAlias ' . $alias;
                    }

                    $serverAdmin = ask('Please enter the server admin?', 'support@webtorque.co.nz');

                    //replace variables
                    $config = str_replace(
                        [
                            '{domain}',
                            '{site_path}',
                            '{aliases}',
                            '{server_admin}'
                        ],
                        [
                            $env['domain'],
                            $env['path'],
                            implode("\n", $aliases),
                            $serverAdmin
                        ],
                        $config
                    );

                    $tmp = '/tmp/apache.conf';
                    $conf = getApacheConfigFile($env['domain']);
                    file_put_contents($tmp, $config);

                    // upload them move file in case we need sudo permissions
                    upload($tmp, "/tmp/{$conf}");
                    sudo("mv /tmp/{$conf} /etc/apache2/sites-available/{$conf}");

                    return;
                } else {
                    throw new \Exception("Detected Apache version {$version['major']}.{$version['minor']}, only 2.4 and 2.2 are supported");
                }
            } else {
                writeln('<info>Apache has already been setup</info>');
            }
        }
    })->desc('Setting up apache');

task(
	'apache:ensite',
	function () {
		$site = '';
        $env = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();

        if (has('domain')) {
            $site = get('domain', '');
        } else {

            if (!$site) {

                if (!($site = $env['domain'])) {
                    $site = ask('Enter the domain for the remote site', '');
                }
            }
        }

        $conf = getApacheConfigFile($site);

        if (!fileExists("/etc/apache2/sites-enabled/{$conf}")) {
            sudo("a2ensite {$site}");
            restartApache();
        } else {
            writeln('<info>Site has already been enabled in Apache</info>');
        }
	}
)->desc('Enabling site');

task(
	'apache:dissite',
	function () {
        $site = '';

        //check if we have stored previously
        if (has('domain')) {
            $site = get('domain');
        } else { //load from remote server
			$env = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();
			if (!($site = $env['domain'])) {
				$site = ask('Enter the domain for the remote site', '');
			}
		}

		sudo("a2dissite {$site}");
		restartApache();
	}
)->desc('Disabling site Apache');

task(
	'apache:restart',
	function () {
		restartApache();
	}
)->desc('Restarting Apache');