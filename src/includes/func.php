<?php
if (!defined('ROOT')) exit('For science.');

// Include classes
require 'Server.class.php';
require 'Plugin.class.php';
require 'Cache.class.php';

// Some constants
define('SECONDS_IN_HOUR', 60 * 60);
define('SECONDS_IN_HALFDAY', 60 * 60 * 12);
define('SECONDS_IN_DAY', 60 * 60 * 24);
define('SECONDS_IN_WEEK', 60 * 60 * 24 * 7);

// Connect to the caching daemon
$cache = new Cache();

/**
 * Get the epoch of the closest hour (downwards, never up)
 * @return float
 */
function getLastHour()
{
    return strtotime(date('F d Y H:00'));
}

/**
 * Calculate the time until the next graph will be calculated
 * @return int the unix timestamp of the next graph
 */
function timeUntilNextGraph()
{
    global $config;

    $interval = $config['graph']['interval'];
    return normalizeTime() + ($interval * 60);
}

/**
 * Normalize a time to the nearest graphing period
 *
 * @param $time if < 0, the time() will be used
 */
function normalizeTime($time = -1)
{
    global $config;

    if ($time < 0)
    {
        $time = time();
    }

    // The amount of minutes between graphing periods
    $interval = $config['graph']['interval'];

    // Calculate the denominator (interval * 60 secs)
    $denom = $interval * 60;

    // Round to the closest one
    return round(($time - ($denom / 2)) / $denom) * $denom;
}

/**
 * Load a key from POST. If it does not exist, die loudly
 *
 * @param $key string
 * @return string
 */
function getPostArgument($key)
{
    // FIXME change to $_POST
    // check
    if (!isset($_POST[$key]))
    {
        exit('ERR Missing arguments.');
    }

    return $_POST[$key];
}

/**
 * Extract custom data from the post request
 * @return array
 */
function extractCustomData()
{
    $custom = array();

    foreach ($_POST as $key => $value)
    {
        // verify we have a number as the key
        if (!is_numeric($value)) {
            continue;
        }

        // check if the string starts with custom
        // note !== note == (false == 0, false !== 0)
        if (stripos($key, 'custom') !== 0) {
            continue;
        }

        $columnName = str_replace('_', ' ', substr($key, 6));
        $columnName = mb_convert_encoding($columnName, 'ISO-8859-1', 'UTF-8');

        if (strstr($columnName, 'Protections') !== FALSE)
        {
            $columnName = str_replace('?', 'i', $columnName);
        }

        if (!in_array($columnName, $custom))
        {
            $custom[$columnName] = $value;
        }
    }

    return $custom;
}

/**
 * Get all of the possible country codes we have stored
 *
 * @return string[], e.g ["CA"] = "Canada"
 */
function loadCountries()
{
    global $pdo;
    $countries = array();

    $statement = $pdo->prepare('SELECT ShortCode, FullName FROM Country LIMIT 300'); // hard limit of 300
    $statement->execute();

    while ($row = $statement->fetch())
    {
        $shortCode = $row['ShortCode'];
        $fullName = $row['FullName'];

        $countries[$shortCode] = $fullName;
    }

    return $countries;
}

/**
 * Loads all of the plugins from the database
 *
 * @return Plugin[]
 */
function loadPlugins()
{
    global $pdo;
    $plugins = array();

    $statement = $pdo->prepare('SELECT ID, Name, Author, Hidden, GlobalHits FROM Plugin ORDER BY (SELECT COUNT(*) FROM ServerPlugin WHERE Plugin = Plugin.ID AND Updated >= ?) DESC');
    $statement->execute(array(time() - SECONDS_IN_DAY));

    while ($row = $statement->fetch())
    {
        $plugin = new Plugin();
        $plugin->setID($row['ID']);
        $plugin->setName($row['Name']);
        $plugin->setAuthor($row['Author']);
        $plugin->setHidden($row['Hidden']);
        $plugin->setGlobalHits($row['GlobalHits']);
        $plugins[] = $plugin;
    }

    return $plugins;
}

/**
 * Load a plugin
 *
 * @param $plugin string The plugin's name
 * @return Plugin if it exists otherwise NULL
 */
function loadPlugin($plugin)
{
    global $pdo;

    $statement = $pdo->prepare('SELECT ID, Name, Author, Hidden, GlobalHits FROM Plugin WHERE Name = :Name');
    $statement->execute(array(':Name' => $plugin));

    if ($row = $statement->fetch())
    {
        $plugin = new Plugin();
        $plugin->setID($row['ID']);
        $plugin->setName($row['Name']);
        $plugin->setAuthor($row['Author']);
        $plugin->setHidden($row['Hidden']);
        $plugin->setGlobalHits($row['GlobalHits']);
        return $plugin;
    }

    return NULL;
}