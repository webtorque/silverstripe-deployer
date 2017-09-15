<?php
/**
 * Include all deployer files
 */
require 'src/helpers.php';
require 'src/Environment.php';
require 'src/apache_tasks.php';
require 'src/db_tasks.php';
require 'src/sync_tasks.php';
require 'src/deploy_tasks.php';
require 'src/teardown_tasks.php';