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

namespace RobiNN\Pca\Dashboards\Redis;

use Exception;
use Predis\Client as Predis;
use RobiNN\Pca\Config;
use RobiNN\Pca\Dashboards\DashboardException;
use RobiNN\Pca\Dashboards\DashboardInterface;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Template;

class RedisDashboard implements DashboardInterface {
    use RedisTrait;

    private Template $template;

    /**
     * @var array<int, array<string, int|string>>
     */
    private array $servers;

    private int $current_server;

    public function __construct(Template $template) {
        $this->template = $template;

        $this->servers = Config::get('redis', []);

        $server = Http::get('server', 'int');

        $this->current_server = array_key_exists($server, $this->servers) ? $server : 0;
    }

    public static function check(): bool {
        return extension_loaded('redis') || class_exists(Predis::class);
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public function dashboardInfo(): array {
        return [
            'key'    => 'redis',
            'title'  => 'Redis',
            'colors' => [
                100 => '#fee2e2',
                200 => '#fecaca',
                300 => '#fca5a5',
                500 => '#ef4444',
                600 => '#dc2626',
                700 => '#b91c1c',
                900 => '#7f1d1d',
            ],
        ];
    }

    /**
     * Connect to the server.
     *
     * @param array<string, int|string> $server
     *
     * @return Compatibility\Redis|Compatibility\Predis
     * @throws DashboardException
     */
    public function connect(array $server) {
        if (extension_loaded('redis')) {
            $redis = new Compatibility\Redis();
        } elseif (class_exists(Predis::class)) {
            $redis = new Compatibility\Predis();
        } else {
            throw new DashboardException('Redis extension or Predis is not installed.');
        }

        if (isset($server['path'])) {
            $redis_server = $server['path'];
        } else {
            $server['port'] ??= 6379;

            $redis_server = $server['host'].':'.$server['port'];
        }

        try {
            if (isset($server['path'])) {
                $redis->connect($server['path']);
            } else {
                $redis->connect($server['host'], (int) $server['port'], 3);
            }
        } catch (Exception $e) {
            throw new DashboardException(
                sprintf('Failed to connect to Redis server %s. Error: %s', $redis_server, $e->getMessage())
            );
        }

        try {
            if (isset($server['password'])) {
                if (isset($server['username'])) {
                    $credentials = [$server['username'], $server['password']];
                } else {
                    $credentials = $server['password'];
                }

                $redis->auth($credentials);
            }
        } catch (Exception $e) {
            throw new DashboardException(
                sprintf('Could not authenticate with Redis server %s. Error: %s', $redis_server, $e->getMessage())
            );
        }

        try {
            $redis->select(Http::get('db', 'int', $server['database'] ?? 0));
        } catch (Exception $e) {
            throw new DashboardException(
                sprintf('Could not select Redis database %s. Error: %s', $redis_server, $e->getMessage())
            );
        }

        return $redis;
    }

    public function ajax(): string {
        $return = '';

        if (isset($_GET['panel'])) {
            $return = Helpers::returnJson($this->serverInfo());
        } else {
            try {
                $redis = $this->connect($this->servers[$this->current_server]);

                if (isset($_GET['deleteall'])) {
                    $return = $this->deleteAllKeys($redis);
                }

                if (isset($_GET['delete'])) {
                    $return = Helpers::deleteKey($this->template, static fn (string $key): bool => $redis->del($key) > 0, true);
                }
            } catch (DashboardException|Exception $e) {
                $return = $e->getMessage();
            }
        }

        return $return;
    }

    public function infoPanels(): string {
        // Hide panels on these pages.
        if (isset($_GET['moreinfo']) || isset($_GET['form']) || isset($_GET['view'], $_GET['key'])) {
            return '';
        }

        if (extension_loaded('redis')) {
            $title = 'PHP Redis extension';
            $version = phpversion('redis');
        } elseif (class_exists(Predis::class)) {
            $title = 'Predis';
            $version = Predis::VERSION;
        }

        return $this->template->render('partials/info', [
            'title'             => $title ?? null,
            'extension_version' => $version ?? null,
            'info'              => [
                'ajax'   => true,
                'panels' => $this->panels(),
            ],
        ]);
    }

    public function dashboard(): string {
        if (count($this->servers) === 0) {
            return 'No servers';
        }

        if (isset($_GET['moreinfo'])) {
            $return = $this->moreInfo();
        } else {
            try {
                $redis = $this->connect($this->servers[$this->current_server]);

                if (isset($_GET['view'], $_GET['key'])) {
                    $return = $this->viewKey($redis);
                } elseif (isset($_GET['form'])) {
                    $return = $this->form($redis);
                } else {
                    $return = $this->mainDashboard($redis);
                }
            } catch (DashboardException|Exception $e) {
                return $e->getMessage();
            }
        }

        return $return;
    }
}
