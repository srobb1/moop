<?php
require_once __DIR__ . '/includes/session_init.php';
moop_session_start();
session_destroy();
header("Location: index.php");
exit();
