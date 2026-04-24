<?php

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

redirect('login.php');
