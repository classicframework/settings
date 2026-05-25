<?php

namespace classicframework\settings;

class Settings
{
  protected $config = array();
  protected $database = null;
  protected $cache = array();
  protected $loaded = false;

  protected $installed = false;

  public function __construct($config = array(), $database = null)
  {
    $this->config = is_array($config) ? $config : array();
    $this->database = $database;
  }

  protected function ensure_installed()
  {
    if ($this->installed) {
      return;
    }

    $this->install();

    $this->installed = true;
  }

  public function set_database($database)
  {
    $this->database = $database;
    return $this;
  }

  public function table()
  {
    $this->ensure_database();

    $table = isset($this->config['table']) ? (string) $this->config['table'] : 'settings';

    return $this->database->table($table);
  }

  public function install()
  {
    $this->ensure_database();

    $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table() . ' (
      id int(10) unsigned NOT NULL AUTO_INCREMENT,
      name varchar(190) NOT NULL,
      value text NULL,
      created_at datetime NOT NULL,
      updated_at datetime NULL,
      PRIMARY KEY (id),
      UNIQUE KEY name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $this->database->execute($sql);

    $this->installed = true;
  }

  public function load()
  {
    $this->ensure_database();
    $this->ensure_installed();

    $rows = $this->database->rows(
      'SELECT name, value FROM ' . $this->table() . ' ORDER BY name ASC'
    );

    $this->cache = array();

    foreach ($rows as $row) {
      if (isset($row['name'])) {
        $this->cache[(string) $row['name']] = isset($row['value']) ? $row['value'] : null;
      }
    }

    $this->loaded = true;

    return $this->cache;
  }

  public function all()
  {
    if (!$this->loaded) {
      $this->load();
    }

    return $this->cache;
  }

  public function get($name, $default = null)
  {
    if (!$this->loaded) {
      $this->load();
    }

    $name = (string) $name;

    if (array_key_exists($name, $this->cache)) {
      return $this->cache[$name];
    }

    return $default;
  }

  public function set($name, $value)
  {
    $this->ensure_database();
    $this->ensure_installed();

    $name = (string) $name;
    $value = $value === null ? null : (string) $value;
    $now = date('Y-m-d H:i:s');

    $exists = $this->database->row(
      'SELECT id FROM ' . $this->table()
      . ' WHERE name = ' . $this->quote($name)
      . ' LIMIT 1'
    );

    if ($exists) {
      $this->database->update(
        $this->raw_table_name(),
        array(
          'value' => $value,
          'updated_at' => $now,
        ),
        array(
          'name' => $name,
        )
      );
    } else {
      $this->database->insert(
        $this->raw_table_name(),
        array(
          'name' => $name,
          'value' => $value,
          'created_at' => $now,
          'updated_at' => null,
        )
      );
    }

    $this->cache[$name] = $value;
    $this->loaded = true;

    return true;
  }

  public function delete($name)
  {
    $this->ensure_database();
    $this->ensure_installed();

    $name = (string) $name;

    $this->database->delete($this->raw_table_name(), array(
      'name' => $name,
    ));

    if (array_key_exists($name, $this->cache)) {
      unset($this->cache[$name]);
    }

    return true;
  }

  protected function raw_table_name()
  {
    return isset($this->config['table']) ? (string) $this->config['table'] : 'settings';
  }

  protected function quote($value)
  {
    return "'" . $this->database->escape($value) . "'";
  }

  protected function ensure_database()
  {
    if (!is_object($this->database)) {
      throw new \Exception('Settings database service is missing.');
    }

    if (!method_exists($this->database, 'execute')) {
      throw new \Exception('Settings database service must have an execute() method.');
    }
  }
}