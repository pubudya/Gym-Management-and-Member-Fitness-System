<?php
declare(strict_types=1);
require __DIR__ . '/includes/init.php';
Auth::logout();
flash('success', 'You have been logged out.');
redirect('index.php');
