<?php
require_once 'admin/includes/auth.php';
session_destroy();
header('Location: /login.php');
exit;
