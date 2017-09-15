<?php

$depFile = 'https://deployer.org/releases/v4.3.1/deployer.phar';

exec("curl -LO {$depFile}");
exec('mv deployer.phar /usr/local/bin/dep');
exec('chmod +x /usr/local/bin/dep');

echo "\nDeployer installed to /usr/local/bin/dep\n";