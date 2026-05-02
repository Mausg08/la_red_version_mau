<?php
session_start();
require_once '../../backend/shared/auth_check.php';
$user = $_SESSION['user'];
$base = '/RedSocial_BUAP';

// Redirigir al feed real que tiene todo el contenido
// Esta página redirige internamente con la base correcta
header('Location: ' . $base . '/frontend/pages/feed.php');
exit;
