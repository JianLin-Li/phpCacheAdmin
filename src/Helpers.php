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

namespace RobiNN\Pca;

use Exception;

class Helpers {
    /**
     * Convert ENV variable to array.
     *
     * It allows to use ENV variables and config.php together.
     *
     * @param array  $array
     * @param string $array_key
     * @param mixed  $value
     *
     * @return void
     */
    public static function envVarToArray(array &$array, string $array_key, $value): void {
        $array_key = str_replace('PCA_', '', $array_key);
        $keys = explode('_', $array_key);
        $keys = array_map('strtolower', $keys);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }

    /**
     * Format bytes.
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function formatBytes(int $bytes): string {
        if ($bytes > 1048576) {
            return sprintf('%.2fMB', $bytes / 1048576);
        }

        if ($bytes > 1024) {
            return sprintf('%.2fkB', $bytes / 1024);
        }

        return sprintf('%dbytes', $bytes);
    }

    /**
     * Format seconds.
     *
     * @param int  $time
     * @param bool $ago
     *
     * @return string
     */
    public static function formatSeconds(int $time, bool $ago = false): string {
        $seconds_in_minute = 60;
        $seconds_in_hour = 60 * $seconds_in_minute;
        $seconds_in_day = 24 * $seconds_in_hour;

        $days = floor($time / $seconds_in_day);

        $hour_seconds = $time % $seconds_in_day;
        $hours = floor($hour_seconds / $seconds_in_hour);

        $minute_seconds = $hour_seconds % $seconds_in_hour;
        $minutes = floor($minute_seconds / $seconds_in_minute);

        //$remainingSeconds = $minute_seconds % $seconds_in_minute;
        //$seconds = ceil($remainingSeconds);

        $time_parts = [];
        $sections = [
            'day'    => (int) $days,
            'hour'   => (int) $hours,
            'minute' => (int) $minutes,
            //'second' => (int) $seconds,
        ];

        foreach ($sections as $name => $value) {
            if ($value > 0) {
                $time_parts[] = $value.' '.$name.($value === 1 ? '' : 's');
            }
        }

        return implode(' ', $time_parts).($ago ? ' ago' : '');
    }

    /**
     * Truncate text.
     *
     * @param string $text
     * @param int    $length
     *
     * @return string
     */
    public static function truncate(string $text, int $length): string {
        if (strlen($text) <= $length) {
            return $text;
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, ($length - 3), 'UTF-8').'...';
        } else {
            $text = substr($text, 0, ($length - 3)).'...';
        }

        return $text;
    }

    /**
     * Return JSON data for ajax.
     *
     * @param array $data
     *
     * @return string
     */
    public static function returnJson(array $data): string {
        header('Content-Type: application/json');

        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return '{"error": "'.$e->getMessage().'"}';
        }
    }

    /**
     * Get svg icon from file.
     *
     * @param string $icon
     * @param int    $size
     *
     * @return ?string
     */
    public static function svg(string $icon, int $size = 16): ?string {
        $file = __DIR__.'/../assets/icons/'.$icon.'.svg';

        if (is_file($file)) {
            $content = trim(file_get_contents($file));
            $attributes = 'width="'.$size.'" height="'.$size.'" fill="currentColor" viewBox="0 0 16 16"';

            return preg_replace('~<svg([^<>]*)>~', '<svg xmlns="http://www.w3.org/2000/svg" '.$attributes.'>', $content);
        }

        return null;
    }

    /**
     * Convert bool to string in array.
     *
     * @param array $array
     *
     * @return array
     */
    public static function convertBoolToString(array $array): array {
        foreach ($array as $name => $value) {
            if (is_array($value)) {
                $array[$name] = self::convertBoolToString($value);
            } elseif (is_bool($value)) {
                $array[$name] = $value ? 'true' : 'false';
            } else {
                $array[$name] = $value;
            }
        }

        return $array;
    }
}
