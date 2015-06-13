<?php

namespace Dropbox\OAuth\Storage;

class SQLite extends PDO
{
    public function connect($file)
    {
        $this->pdo = new \PDO('sqlite:' . $file);
        if (!file_exists($file) || 0 == filesize($file)) {
            $this->createTable();
        }
    }

    protected function insertToken($token)
    {
        $query = 'INSERT OR REPLACE INTO ' . $this->table . ' (userID, token) VALUES (?, ?)';
        $stmt = $this->pdo->prepare($query);
        $token = $this->encrypt($token);
        $stmt->execute(array($this->userID, $token));
    }

    protected function createTable()
    {
        $template = file_get_contents(dirname(__FILE__) . '/TableSchemaSQLite.sql');
        $this->pdo->query(sprintf($template, $this->table));
    }
}
