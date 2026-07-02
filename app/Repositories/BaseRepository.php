<?php

namespace DentiSoft\App\Repositories;

use PDO;

abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }
}
