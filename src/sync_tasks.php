<?php
namespace Deployer;
/**
 * Sync from remote server to local
 */

desc('Downloads remote database');
task(
    'sync:db',
    function() {
        $filename = 'db/' . date('Y-m-d-H-s') . '.sql';
        $credentials = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();

        if (!directoryExists('db')) {
            run("mkdir db");
        }

        writeln('<info>Dumping remote database</info>');
        $remoteFilename = '{{deploy_path}}' . DIRECTORY_SEPARATOR . $filename;

        // generate mysql command
        $command = generateMySQLCommand(
            $credentials['username'],
            $credentials['database'],
            get('db_host', null),
            get('db_port', null),
            true
        );

        run("$command {$credentials['database']} > {$remoteFilename}");

        writeln('<info>Downloading database file</info>');
        download($filename, $remoteFilename);

        run("rm {$remoteFilename}");

        //store so db:import can pick it up
        set('db_file', $filename);
    }
);


task(
	'sync:assets_remote',
	function() {
		askConfirmation('This will compress the assets folder on the remote server, make sure there is enough disk space before continuing');

		$remoteAssets = '{{deploy_path}}/shared/' . get('web_dir');
        $localAssets = get('web_dir');
		$tmpFile = '/tmp/assets.tgz';

        writeln('<info>Compressing assets</info>');
		runLocally("cd {$localAssets} && tar -czf {$tmpFile} assets");

        writeln('<info>Uploading compressed assets</info>');
		upload($tmpFile, $tmpFile);
		runLocally("rm {$tmpFile}");

        writeln('<info>Setting up remote assets</info>');
		run("rm -Rf {$remoteAssets}/assets");
		run("cd /tmp && tar -xzf {$tmpFile}");
		run("mv /tmp/assets {$remoteAssets}/assets");
		run("rm {$tmpFile}");
	}
)->desc('Uploads local assets to remote server');

desc('Download remote assets directory and install locally');
task(
    'sync:assets',
    function() {
        askConfirmation('This will compress the assets folder on the remote server, make sure there is enough disk space before continuing');

        $webDir = get('web_dir', './') . '/';
        $remoteShareDir = '{{deploy_path}}/shared/' . $webDir . '/';
        $tmpFile = '/tmp/assets.tgz';

        writeln('<info>Compressing assets</info>');
        run("cd {$remoteShareDir} && tar -czf {$tmpFile} assets");

        writeln('<info>Downloading compressed assets</info>');
        download($tmpFile, $tmpFile);
        run("rm {$tmpFile}");

        writeln('<info>Setting up local assets</info>');
        runLocally("rm -Rf {$webDir}/assets");
        runLocally("cd /tmp && tar -xzf {$tmpFile}");
        runLocally("mv /tmp/assets {$webDir}assets");
        runLocally("rm {$tmpFile}");
    }
);


task(
    'sync:uploaddb',
    function(){
        $file = get('db_file', function() {
            return ask('Enter the path to the file to import', '');
        });

        upload($file, '{{deploy_path}}/' . $file);
    }
)->desc('Uploading database to remote server');

task(
	'sync:cleanup',
	function() {
		if ($dbFile = get('db_file')) {
			runLocally("rm {$dbFile}");
		}

	}
)->desc('Cleanup import files');


task(
	'sync:local',
	[
		'sync:db',
		'db:import',
		'sync:assets',
		'sync:cleanup'
	]
)->desc('Download and setup database and assets');


/**
 * Sync from local to remote server
 */
task(
    'sync:remote',
    [
        'db:export',
        'sync:uploaddb',
        'db:import_remote',
        'sync:assets_remote',
        'sync:cleanup'
    ]
)->desc('Upload local db/assets to remote server');


/**
 * Sync from local to remote server
 */
task(
	'sync:remotedb',
	[
		'db:export',
		'sync:uploaddb',
		'db:import_remote',
		'sync:cleanup'
	]
)->desc('Upload local db to remote server');