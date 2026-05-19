<?php require_once 'includes/functions.php'; if(is_logged_in()) redirect(BASE . '/dashboard.php'); else redirect(BASE . '/auth/login.php'); ?>
