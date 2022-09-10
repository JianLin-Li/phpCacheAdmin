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

namespace Tests\Dashboards;

use Redis;
use RedisException;
use RobiNN\Pca\Dashboards\Redis\RedisDashboard;
use RobiNN\Pca\Http;
use RobiNN\Pca\Template;
use Tests\TestCase;

final class RedisTest extends TestCase {
    private Template $template;

    private RedisDashboard $dashboard;

    private Redis $redis;

    protected function setUp(): void {
        $this->template = new Template();
        $this->dashboard = new RedisDashboard($this->template);
        $this->redis = self::callMethod($this->dashboard, 'connect', ['host' => '127.0.0.1', 'database' => 10]);
    }

    /**
     * @throws RedisException
     */
    public function testDeleteKey(): void {
        $key = 'pu-test-delete-key';

        $this->redis->set($key, 'data');

        $_GET['delete'] = $key;

        $this->assertSame(
            $this->template->render('components/alert', ['message' => 'Key "'.$key.'" has been deleted.']),
            self::callMethod($this->dashboard, 'deleteKey', $this->redis)
        );
        $this->assertSame(0, $this->redis->exists($key));
    }

    /**
     * @throws RedisException
     */
    public function testDeleteKeys(): void {
        $key1 = 'pu-test-delete-key1';
        $key2 = 'pu-test-delete-key2';
        $key3 = 'pu-test-delete-key3';

        $this->redis->set($key1, 'data1');
        $this->redis->set($key2, 'data2');
        $this->redis->set($key3, 'data3');

        $_GET['delete'] = implode(',', [$key1, $key2, $key3]);

        $this->assertSame(
            $this->template->render('components/alert', ['message' => 'Keys has been deleted.']),
            self::callMethod($this->dashboard, 'deleteKey', $this->redis)
        );
        $this->assertSame(0, $this->redis->exists($key1));
        $this->assertSame(0, $this->redis->exists($key2));
        $this->assertSame(0, $this->redis->exists($key3));
    }

    /**
     * @throws RedisException
     */
    public function testGetKey(): void {
        $keys = [
            'string' => ['original' => 'phpCacheAdmin', 'expected' => 'phpCacheAdmin'],
            'int'    => ['original' => 23, 'expected' => '23'],
            'float'  => ['original' => 23.99, 'expected' => '23.99'],
            'bool'   => ['original' => true, 'expected' => '1'],
            'null'   => ['original' => null, 'expected' => ''],
            'array'  => [
                'original' => serialize(['key1', 'key2']),
                'expected' => 'a:2:{i:0;s:4:"key1";i:1;s:4:"key2";}',
            ],
            'object' => [
                'original' => serialize((object) ['key1', 'key2']),
                'expected' => 'O:8:"stdClass":2:{s:1:"0";s:4:"key1";s:1:"1";s:4:"key2";}',
            ],
        ];

        foreach ($keys as $key => $value) {
            $this->redis->set('pu-test-'.$key, $value['original']);
        }

        $this->assertSame($keys['string']['expected'], $this->redis->get('pu-test-string'));
        $this->assertSame($keys['int']['expected'], $this->redis->get('pu-test-int'));
        $this->assertSame($keys['float']['expected'], $this->redis->get('pu-test-float'));
        $this->assertSame($keys['bool']['expected'], $this->redis->get('pu-test-bool'));
        $this->assertSame($keys['null']['expected'], $this->redis->get('pu-test-null'));
        $this->assertSame($keys['array']['expected'], $this->redis->get('pu-test-array'));
        $this->assertSame($keys['object']['expected'], $this->redis->get('pu-test-object'));

        foreach ($keys as $key => $value) {
            $this->redis->del('pu-test-'.$key);
        }
    }

    /**
     * @throws RedisException
     */
    public function testSaveKey(): void {
        $key = 'pu-test-save';

        $_POST['redis_type'] = 'string';
        $_POST['key'] = $key;
        $_POST['value'] = 'test-value';
        $_POST['expire'] = -1;
        $_POST['encoder'] = 'none';

        Http::stopRedirect();
        self::callMethod($this->dashboard, 'saveKey', $this->redis);

        $this->assertSame('test-value', $this->redis->get($key));

        $this->redis->del($key);
    }
}
