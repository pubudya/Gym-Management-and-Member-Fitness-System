<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');
flash('info', 'Membership plans were removed. Members pay one monthly gym fee, which you can edit in Settings.');
redirect('admin/settings.php');
