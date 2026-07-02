<?php
/**
 * Cierre de sesion del Portal del Paciente.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

clearPatientSession();
session_regenerate_id(true);

header('Location: ' . BASE_URL . '/portal-login.php');
exit;
