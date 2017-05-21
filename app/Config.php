<?php

/**
 * Setup the Storage Path.
 */
define('STORAGE_PATH', BASEPATH .'storage' .DS);

/**
 * PREFER to be used in Database calls or storing Session data, default is 'mini_'
 */
define('PREFIX', 'mini_');

/**
 * Setup the Config API Mode.
 * For using the 'database' mode, you need to have a database, with a table generated by 'scripts/mini_options'
 */
define('CONFIG_STORE', 'files'); // Supported: "files", "database"
