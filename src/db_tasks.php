<?php
namespace Deployer;
use \WebTorque\Deployment\Environment;

set('db_admin_password', function() {
   return ask('What is the admin password for the database?');
});

desc('Importing database');
task(
    'db:import',
    function() {
        $file = get('db_file');

        if (!$file) $file = ask('Enter the path to the file to import', '');

        writeln('<info>Importing database</info>');

        $credentials = Environment::inst(file_get_contents('_ss_environment.php'))->parseEnvironment();

        runLocally("mysql -u {$credentials['username']} -p{$credentials['password']} < {$file}");
    }
);

desc('Importing database on remote server');
task(
    'db:import_remote',
    function() {
        $file = get('db_file');

        if (!$file) $file = ask('Enter the path to the file to import', '');

        writeln('<info>Importing database</info>');

        $env = Environment::inst(getRemoteEnvironment())->parseEnvironment();

        if (!databaseExixts($env['database']) || askConfirmation('Are you sure you want to overwrite the database')) {
            run(getMySQLCommand("{$env['database']} < {{deploy_path}}/{$file}"));
        }
    }
);

task(
    'db:permissions',
    function() {
        $env = Environment::inst(getRemoteEnvironment())->parseEnvironment();

        $sql1 = "CREATE DATABASE IF NOT EXISTS {$env['database']}";
        $sql2 = "GRANT ALL ON {$env['database']}.* TO '{$env['username']}'@'localhost' IDENTIFIED BY '{$env['password']}'";

        run(getMySQLCommand("-e \"{$sql1}\""));
        run(getMySQLCommand("-e \"{$sql2}\""));
    }
)->desc('Setting permissions on database');

task(
    'db:export',
    function() {
        $filename = 'db/' . date('Y-m-d-H-s') . '.sql';

        $credentials = Environment::inst(file_get_contents('_ss_environment.php'))->parseEnvironment();

        runLocally("mysqldump -u {$credentials['username']} -p{$credentials['password']} {$credentials['database']} > {$filename}");

        //set filename so other task can use it
        set('db_file', $filename);
    }
);

task('db:exists',
    function() {
        $db = ask('What database would you like to check?', '');

        if (databaseExixts($db)) {
            writeln('<info>This database already exists</info>');
        } else {
            writeln('<info>This database doesn\'t exist</info>');
        }
    }
);