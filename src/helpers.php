<?php
namespace Deployer;

use Deployer\Task\Context;

/**
 * Checks if given file path exists
 *
 * @param $path
 * @return bool
 */
function fileExists($path)
{
    return run("if test -f \"{$path}\"; then echo 'true'; else echo 'false'; fi")->toBool();
}

/**
 * Checks if given directory path exists
 *
 * @param $path
 * @return bool
 */
function directoryExists($path)
{
    return run("if test -d \"{$path}\"; then echo 'true'; else echo 'false'; fi")->toBool();
}


function databaseExists($database)
{
    $user = ask('What is the admin user for the database? (default root)', 'root');
    $password = askHiddenResponse('What is the root password for the database?');
    return run("if ! mysql -u {$user} -p{$password} -e 'use {$database}'; then echo 'false'; else echo 'true'; fi")->toBool();
}

/**
 * Returns the version of Apache
 *
 * <code>
 * $version = array(
 *      'major' => 2,
 *      'minor' => 4
 *      'revision' => 9
 * );
 * </code>
 *
 * @return array|null
 */
function apacheVersion()
{
    $response = sudo("apache2ctl -v");

    preg_match('/Apache\\/(\d+)?\.(\d+)?\.(\*|\d+)/i', $response, $match);

    if (count($match) === 4) {
        $major = $match[1];
        $minor = $match[2];
        $revision = $match[3];

        $version = array(
            'major' => (int)$major,
            'minor' => (int)$minor,
            'revision' => (int)$revision
        );

        set('apache_version', $version);

        return $version;
    }

    return null;
}

/**
 * Checks if Apache config has already been setup for given domain
 *
 * @param $domain
 * @return bool
 */
function apacheSetup($domain)
{
    return fileExists('/etc/apache2/sites-enabled/' . getApacheConfigFile($domain));
}

/**
 * Restarts the Apache server, tests for a valid config first
 *
 * @throws Exception
 */
function restartApache()
{
    writeln('');
    writeln('<info>Testing Apache config</info>');
    $response = trim(sudo('apache2ctl configtest'));
    $test = strtolower($response) === 'syntax ok';

    if ($test) {
        writeln('<info>Gracefully restarting Apache</info>');
        sudo('service apache2 graceful');
    } else {
        throw new \Exception("There is an error in the Apache config\n\n{$response}");
    }

}

//set('apache_version', '');
/**
 * Returns the name of the apache config file, {domain}.conf for 2.4, {domain} for 2.2
 * @param $domain
 * @return string
 */
function getApacheConfigFile($domain)
{
    $version = get('apache_version');

    if (!$version) {
        $version = apacheVersion();
    }

    return $version['major'] === 2 && $version['minor'] === 4
        ? "{$domain}.conf"
        : "{$domain}";
}

set('remote_env', '');
/**
 * Gets the contents of the remote _ss_environment.php file
 * @return string
 */
function getRemoteEnvironment()
{

    if ($env = get('remote_env')) {
        return $env;
    }

    $tmp = '/tmp/env.php';
    $remoteEnv = Context::get()->getEnvironment()->parse("{{deploy_path}}/shared/_ss_environment.php");

    writeln('');
    writeln("<info>Getting remote credentials from {$remoteEnv}</info>");
    download($tmp, $remoteEnv);
    writeln('<info>Remote environment downloaded</info>');
    $env = file_get_contents($tmp);
    unlink($tmp);

    set('remote_env', $env);

    return $env;
}


/**
 * Generates mysql commandline command.
 *
 * @param string  $username Username for database.
 * @param string  $password Password for database.
 * @param string  $host     Host for database.
 * @param int     $port     Port for database.
 * @parab boolean $dumb     Whether this is a mysqldump command
 * @return string
 */
function generateMySQLCommand($username, $password, $host = null, $port = null, $dump = false) {
    $command = $dump ? 'mysqldump' : 'mysql';

    if ($host) {
        $host = " -h {$host}";
    }

    if ($port) {
        $port = " -P {$port}";
    }
    return "{$command} -u {$username} -p{$password}{$host}{$port}";
}

function getMySQLCommand($command, $dump = false) {
    return generateMySQLCommand(
        get('db_admin_user'),
        get('db_admin_password'),
        get('db_host', null),
        get('db_port', null)
    ) . ' ' . $command;
}

/**
 * Applies sudo to a command if required and executes it
 *
 * @param string  $command The command to run.
 * @param boolean $local   Whether the command should be run locally.
 *
 * @return string
 */
function sudo($command, $local = false) {
    $sudo = get('use_sudo') ? 'sudo ' : '';
    $command = "{$sudo}{$command}";

    return $local ? runLocally($command) : run($command);
}