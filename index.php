<?php
/**
 * This file is part of phpCacheAdmin.
 *
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use RobiNN\Pca\Admin;
use RobiNN\Pca\Http;
use RobiNN\Pca\Template;

require_once __DIR__.'/vendor/autoload.php';

$tpl = new Template();
$admin = new Admin($tpl);

$nav = [];

foreach ($admin->getDashboards() as $d_key => $d_dashboard) {
    $d_info = $d_dashboard->getDashboardInfo();
    $nav[$d_key] = $d_info['title'];
}

$current = $admin->currentDashboard();
$dashboard = $admin->getDashboard($current);
$info = $dashboard->getDashboardInfo();

$tpl->addTplGlobal('current', $current);
$tpl->addTplGlobal('color', $info['color']);

if (isset($_GET['ajax'])) {
    echo $dashboard->ajax();
} else {
    echo $tpl->render('layout', [
        'site_title' => $info['title'],
        'nav'        => $nav,
        'version'    => Admin::VERSION,
        'back'       => isset($_GET['moreinfo']) || isset($_GET['view']) || isset($_GET['form']),
        'back_url'   => Http::queryString(['db']),
        'panels'     => $dashboard->showPanels(),
        'dashboard'  => $dashboard->dashboard(),
    ]);
}
