<?php
require_once 'config/db.php';
session_destroy();
header("Location: landing.php");
exit();