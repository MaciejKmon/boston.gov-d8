<?php

/**
 * @file
 * Defines the default settings for the website.
 * In public Repo.
 */

/** Copied from default.settings.php Drupal v8.6.4 */

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 *
 * This variable will be set to a random value by the installer. All one-time
 * login links will be invalidated if the value is changed. Note that if your
 * site is deployed on a cluster of web servers, you must ensure that this
 * variable has the same value on each server.
 *
 * For enhanced security, you may set this variable to the contents of a file
 * outside your document root; you should also ensure that this file is not
 * stored with backups of your database.
 *
 * Example:
 * @code
 *   $settings['hash_salt'] = file_get_contents('/home/example/salt.txt');
 * @endcode
 */
$settings['hash_salt'] = 'ivciasdbopasvbdcpasdiv';

/**
 * Access control for update.php script.
 *
 * If you are updating your Drupal installation using the update.php script but
 * are not logged in using either an account with the "Administer software
 * updates" permission or the site maintenance account (the account that was
 * created during installation), you will need to modify the access check
 * statement below. Change the FALSE to a TRUE to disable the access check.
 * After finishing the upgrade, be sure to open this file again and change the
 * TRUE back to a FALSE!
 */
$settings['update_free_access'] = FALSE;

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/default/services.yml';

/**
 * The default list of directories that will be ignored by Drupal's file API.
 *
 * By default ignore node_modules and bower_components folders to avoid issues
 * with common frontend tools and recursive scanning of directories looking for
 * extensions.
 *
 * @see \Drupal\Core\File\FileSystemInterface::scanDirectory()
 * @see \Drupal\Core\Extension\ExtensionDiscovery::scanDirectory()
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * The default number of entities to update in a batch process.
 *
 * This is used by update and post-update functions that need to go through and
 * change all the entities on a site, so it is useful to increase this number
 * if your hosting configuration (i.e. RAM allocation, CPU speed) allows for a
 * larger number of entities to be processed in a single batch run.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Entity update backup.
 *
 * This is used to inform the entity storage handler that the backup tables as
 * well as the original entity type and field storage definitions should be
 * retained after a successful entity update process.
 */
$settings['entity_update_backup'] = TRUE;

/**
 * Node migration type.
 *
 * This is used to force the migration system to use the classic node migrations
 * instead of the default complete node migrations. The migration system will
 * use the classic node migration only if there are existing migrate_map tables
 * for the classic node migrations and they contain data. These tables may not
 * exist if you are developing custom migrations and do not want to use the
 * complete node migrations. Set this to TRUE to force the use of the classic
 * node migrations.
 */
$settings['migrate_node_migrate_type_classic'] = FALSE;

/**
 * Private file path:
 *
 * A local file system path where private files will be stored. This directory
 * must be absolute, outside of the Drupal installation directory and not
 * accessible over the web.
 *
 * Note: Caches need to be cleared when this value is changed to make the
 * private:// stream wrapper available to the system.
 *
 * See https://www.drupal.org/documentation/modules/file for more information
 * about securing private files.
 */
$settings['file_private_path'] = 'sites/default/files/private';

/* End of default.settings.php copy. */

/* Define and set an environment variable for prod/dev mode. */
global $_envvar;

/*
 * Exclude some modules' configurations.
 * The config for these modules will not be exported during a cex, even if the
 * configs have been changed locally.
 * NOTE: the module config_ignore does a similar thing, but ignores specific
 * configs yml files on import (i.e. cim) - with config_ignore, exports (i.e.
 * cex) will still create the config yml files, which is a bit dangerous b/c
 * they could then be inadvertently pushed to git.
 * @see https://www.drupal.org/node/3079028 - d8 core module excludes
 * @see https://www.drupal.org/project/config_ignore - config_ignore
 */
$settings['config_exclude_modules'] = [];

/*
 * Disable all config_split settings at this point.
 * Environment specific settings.php files will override these values and
 * enable the appropriate settings profile.
 */
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.travis']['status'] = FALSE;
$config['config_split.config_split.acquia_dev']['status'] = FALSE;
$config['config_split.config_split.acquia_stage']['status'] = FALSE;
$config['config_split.config_split.acquia_prod']['status'] = FALSE;
$config['config_split.config_split.never_import']['status'] = FALSE;

// Manually set/override the PHP memory limit.
// Note: this may be ignored on Acquia.
ini_set('memory_limit', '1024M');
if ((isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'entity_clone') !== FALSE) || (isset($_SERVER['REDIRECT_URL']) && strpos($_SERVER['REDIRECT_URL'], 'entity_clone') !== FALSE)) {
  ini_set('memory_limit', '-1');
}

/**
 * Location of the site configuration files.
 *
 * The $settings['config_sync_directory'] specifies the location of file system
 * directory used for syncing configuration data. On install, the directory is
 * created. This is used for configuration imports.
 *
 * The default location for this directory is inside a randomly-named
 * directory in the public files path. The setting below allows you to set
 * its location.
 *
 * NOTE: $config_directories["sync"] is deprecated in Drupal 9
 */
$settings['config_sync_directory'] = "../config/default";

/*
 * Include the default local settings file if running locally (in docker).
 */
if (file_exists('/app/docroot')) {

  /* Set flag that we are in development mode. */
  $_envvar = 'dev';

  if (file_exists(DRUPAL_ROOT . '/' . $site_path . '/settings/settings.local.php')) {
    include DRUPAL_ROOT . '/' . $site_path . '/settings/settings.local.php';
  }
}

/*
 * Include the Acquia specific settings if running on Acquia.
 */
elseif (file_exists('/var/www/site-php')) {
  // If this is on an aquia hosted server,

  /* Set flag that we are in production mode. */
  $_envvar = "prod";

  // a custom settings file will exist and will redefine the sql server
  // parameters such as $databases['default']['default'] and other
  // acquia-specific configuration pairs.
  require DRUPAL_ROOT . '/' . $site_path . '/settings/settings.acquia.php';
}

/*
 * Include the Travis specific settings if running on Travis.
 */
elseif (file_exists('/home/travis/build')) {
  /* Set flag that we are in development mode. */
  $_envvar = 'dev';

  // a custom settings file will exist and will redefine the sql server
  // parameters such as $databases['default']['default'] and other
  // travis-specific configuration pairs.
  require DRUPAL_ROOT . '/' . $site_path . '/settings/settings.travis.php';
}

/* Always include the Salesforce settings file. */
if (file_exists(DRUPAL_ROOT . '/' . $site_path . '/settings/salesforce.settings.php')) {
  include DRUPAL_ROOT . '/' . $site_path . '/settings/salesforce.settings.php';
}
// Adds a directive to include contents of settings file in repo.
if (file_exists(DRUPAL_ROOT . '/' . $site_path . 'settings/private.settings.php')) {
  include DRUPAL_ROOT . "/docroot/sites/default/settings/private.settings.php";
}
