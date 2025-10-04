<?php
// app/models/baseModel.php
// BaseModel adaptado a mysqli usando getConexion() definido en conexion.php

require_once __DIR__ . '/conexion.php';

class BaseModel {
    /** @var mysqli */
    protected $db;

    public function __construct() {
        // getConexion() devuelve instancia mysqli (singleton)
        $this->db = getConexion();
    }
}
