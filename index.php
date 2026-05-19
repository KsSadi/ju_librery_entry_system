<?php require_once 'includes/functions.php'; if(is_logged_in()) redirect('/library_entry_system/dashboard.php'); else redirect('/library_entry_system/auth/login.php'); ?>
