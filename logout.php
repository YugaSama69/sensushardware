<?php

require_once __DIR__ . '/config/app.php';

logout_user();
set_flash('success', 'Anda berhasil logout.');
redirect('login.php');
