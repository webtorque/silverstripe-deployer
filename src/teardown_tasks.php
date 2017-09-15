<?php
namespace Deployer;

task('teardown:database',
    function () {
        $env = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();
        run("mysql -u {$env['username']} -p{$env['password']} -e \"drop database {$env['database']}\"");
    }
)->desc('Dropping database');

task('teardown:apache',
    function () {
        $env = \WebTorque\Deployment\Environment::inst(getRemoteEnvironment())->parseEnvironment();

        if (!empty($env['domain'])) {
            $conf = getApacheConfigFile($env['domain']);
            run("rm -f /etc/apache2/sites-available/{$conf}");
        }
    }
)->desc('Removing apache config');

task('teardown:site',
    function () {
        run("rm -Rf {{deploy_path}}");
    }
)->desc('Removing site');

task('teardown', [
    'apache:dissite',
    'teardown:apache',
    'teardown:database',
    'teardown:site'
])->desc('Tearing down remote site');