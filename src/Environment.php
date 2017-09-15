<?php

namespace WebTorque\Deployment;

use function Deployer\ask;
use const PHP_EOL;

class Environment
{

    protected static $defaultDbType = 'MySQLDatabase';

    private $file = null;

    /**
     * @param $file string Contents of _ss_environment.php
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    public static function inst($file)
    {
        $env = new Environment($file);
        return $env;
    }

    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }


    /**
     * Sets up _ss_environment file
     *
     * @param $siteRoot string Full path to root of website
     * @param $username string Database username
     * @param $password string Database password
     * @param $database string Database name
     * @param $domain   string Domain of website
     * @param $ssEnv    string The SS environment type (dev,test,live)
     *
     * @return string
     */
    public function setupEnvironmentFile($siteRoot, $username, $password, $database, $domain, $ssEnv = 'dev')
    {
        $file = $this->file;

        $file = preg_replace("/(define\\('SS_DATABASE_USERNAME'), '(.*?)'\\)/", "$1, '{$username}')", $file);
        $file = preg_replace("/(define\\('SS_DATABASE_PASSWORD'), '(.*?)'\\)/", "$1, '{$password}')", $file);
        $file = preg_replace("/(define\\('SS_DATABASE_NAME'), '(.*?)'\\)/", "$1, '{$database}')", $file);
        $file = preg_replace("/(define\\('SS_ENVIRONMENT_TYPE'), '(.*?)'\\)/", "$1, '{$ssEnv}')", $file);
        $file = preg_replace(
            "/\\\$_FILE_TO_URL_MAPPING\\['.*'?\\] = '.*?'/",
            //normal path - don't change as this we get picked up by parser for other scripts
            "\$_FILE_TO_URL_MAPPING['{$siteRoot}'] = 'http://{$domain}';\n" .
            //setup release directory
            (stripos($siteRoot, 'current') !== false && ($root = str_replace('current', 'release',
                $siteRoot)) ? "\$_FILE_TO_URL_MAPPING['{$root}'] = 'http://{$domain}';\n" : '') .
            //get the realpath as the symlink will get resolved by PHP
            "\$_FILE_TO_URL_MAPPING[realpath('{$siteRoot}')] = 'http://{$domain}'",
            $file
        );

        return $file;
    }

    /**
     * Prompts user for config variables for environment file
     *
     * @return array
     */
    public static function collectConfig()
    {
        return [
            'domain' => ask('Please provide a domain for this server', ''),
            'dbuser' => ask('Please enter the db user:'),
            'dbpassword' => ask('Please enter the db password:'),
            'dbname' => ask('Please enter the db name:'),
            'dbhost' => ask('Please enter the db host (optional)'),
            'dbport' => ask('Please enter the db port (optional)'),
            'dbtype' => ask('Please enter the db type (default ' . self::$defaultDbType . ')'),
            'envtype' => ask('Please enter the environment type (dev/test/live)')
        ];
    }


    public static function generateFromConfig($siteRoot, array $config)
    {
        // default db type
        if (empty($config['dbtype'])) {
            $config['dbtype'] = self::$defaultDbType;
        }

        // initial env
        $envFile = <<<ENV
<?php
define('SS_ENVIRONMENT_TYPE', '{$config['envtype']}');

define('SS_DATABASE_USERNAME', '{$config['dbuser']}');
define('SS_DATABASE_PASSWORD', '{$config['dbpassword']}');
define('SS_DATABASE_NAME', '{$config['dbname']}');
define('SS_DATABASE_CLASS', '{$config['dbtype']}');
ENV;

        // check for host
        if (!empty($config['dbhost'])) {
            $envFile .= "define('SS_DATABASE_SERVER', '{$config['dbhost']}');\n";
        }

        // check for port
        if (!empty($config['dbport'])) {
            $envFile .= "define('SS_DATABASE_PORT', '{$config['dbport']}');\n";
        }

        // add a newline to make more readable
        $envFile .= PHP_EOL;

        // file mapping for dev/build
        // normal path - don't change as this we get picked up by parser for other scripts
        $envFile .= "\$_FILE_TO_URL_MAPPING['{$siteRoot}'] = 'http://{$config['domain']}';\n";

        // realpath for release path
        $releasePath = str_replace('current', 'release', $siteRoot);
        $envFile .= "\$_FILE_TO_URL_MAPPING[realpath('{$releasePath}')] = 'http://{$config['domain']}';\n";

        // realpath for current path
        $envFile .= "\$_FILE_TO_URL_MAPPING[realpath('{$siteRoot}')] = 'http://{$config['domain']}';\n";

        return $envFile;
    }

    public function parseEnvironment()
    {
        $env = $this->file;
        $credentials = [];

        //get db user
        preg_match("/define\\('SS_DATABASE_USERNAME', '(.*?)'\\)/", $env, $matches);
        if ($matches) {
            $credentials['username'] = $matches[1];
        }
        unset($matches);

        preg_match("/define\\('SS_DATABASE_PASSWORD', '(.*?)'\\)/", $env, $matches);
        if ($matches) {
            $credentials['password'] = $matches[1];
        }
        unset($matches);

        preg_match("/define\\('SS_DATABASE_NAME', '(.*?)'\\)/", $env, $matches);
        if ($matches) {
            $credentials['database'] = $matches[1];
        }
        unset($matches);

        preg_match("/define\\('SS_DATABASE_SERVER', '(.*?)'\\)/", $env, $matches);
        if ($matches) {
            $credentials['host'] = $matches[1];
        }
        unset($matches);

        preg_match("/define\\('SS_DATABASE_PORT', '(.*?)'\\)/", $env, $matches);
        if ($matches) {
            $credentials['port'] = $matches[1];
        }
        unset($matches);

        preg_match("/\\\$_FILE_TO_URL_MAPPING\\['(.*?)'\\] \\= 'http\\:\\/\\/(.*?)'/", $env, $matches);

        if ($matches) {
            $credentials['path'] = $matches[1];
            $credentials['domain'] = $matches[2];

            if (stripos($credentials['path'], 'realpath') !== false) {
                preg_match("/\\('(.*?)'\\)/", $credentials['path'], $matches);

                if ($matches) {
                    $credentials['path'] = $matches[1];
                }
            }
        }

        return $credentials;
    }
}
