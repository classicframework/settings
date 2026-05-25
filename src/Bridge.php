<?php

namespace classicframework\settings;

use classicframework\core\App;
use classicframework\core\Config;
use classicframework\core\BridgeInterface;

class Bridge implements BridgeInterface
{
  public static function register(App $app)
  {
    $database = $app->get_service('db');

    if ($database === null) {
      $database = $app->get_service('database');
    }

    $config = Config::extract('settings');

    $settings = new Settings($config, $database);

    $app->set_service('settings', $settings);
  }
}