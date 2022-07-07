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
     * @param array<string, mixed> $array
     * @param string               $array_key
     * @param mixed                $value
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
     * @param int $time
     *
     * @return string
     */
    public static function formatSeconds(int $time): string {
        $seconds_in_minute = 60;
        $seconds_in_hour = 60 * $seconds_in_minute;
        $seconds_in_day = 24 * $seconds_in_hour;

        $days = floor($time / $seconds_in_day);

        $hour_seconds = $time % $seconds_in_day;
        $hours = floor($hour_seconds / $seconds_in_hour);

        $minute_seconds = $hour_seconds % $seconds_in_hour;
        $minutes = floor($minute_seconds / $seconds_in_minute);

        $remainingSeconds = $minute_seconds % $seconds_in_minute;
        $seconds = ceil($remainingSeconds);

        $time_parts = [];
        $sections = [
            'day'    => (int) $days,
            'hour'   => (int) $hours,
            'minute' => (int) $minutes,
            'second' => (int) $seconds,
        ];

        foreach ($sections as $name => $value) {
            if ($value > 0) {
                $time_parts[] = $value.' '.$name.($value === 1 ? '' : 's');
            }
        }

        return implode(' ', $time_parts);
    }

    /**
     * Return JSON data for ajax.
     *
     * @param array<mixed, mixed> $data
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
     * Show alert.
     *
     * @param Template $template
     * @param string   $message
     * @param ?string  $color
     *
     * @return void
     */
    public static function alert(Template $template, string $message, ?string $color = null): void {
        $template->addTplGlobal('alerts', $template->render('components/alert', [
            'message'     => $message,
            'alert_color' => $color,
        ]));
    }

    /**
     * Show enabled/disabled badge.
     *
     * @param Template            $template
     * @param bool                $enabled
     * @param ?string             $text
     * @param ?array<int, string> $badge_text
     *
     * @return string
     */
    public static function enabledDisabledBadge(Template $template, bool $enabled = true, ?string $text = null, ?array $badge_text = null): string {
        $badge_text = $badge_text ?: ['Enabled', 'Disabled'];

        return $template->render('components/badge', [
            'text' => $enabled ? $badge_text[0].$text : $badge_text[1],
            'bg'   => $enabled ? 'bg-green-600' : 'bg-red-600',
            'pill' => true,
        ]);
    }

    /**
     * Convert bool to string in array.
     *
     * @param array<mixed, mixed> $array
     *
     * @return array<mixed, mixed>
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

    /**
     * Checks if a string starts with a given substring.
     *
     * From Symfony polyfills.
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public static function str_starts_with(string $haystack, string $needle): bool {
        if (!function_exists('str_starts_with')) {
            return 0 === strncmp($haystack, $needle, strlen($needle));
        }

        return str_starts_with($haystack, $needle);
    }

    /**
     * Decode and format key value.
     *
     * @param string $value
     *
     * @return array<mixed, mixed>
     */
    public static function decodeAndFormatValue(string $value): array {
        $is_decoded = false;
        $is_formatted = false;

        if (self::decodeValue($value) !== null) {
            $value = (string) self::decodeValue($value);
            $is_decoded = true;
        }

        if (self::formatValue($value) !== null) {
            $value = (string) self::formatValue($value);
            $is_formatted = true;
        }

        $value = self::formatJson($value);

        return [$value, $is_decoded, $is_formatted];
    }

    /**
     * Decode value.
     *
     * @param string $value
     *
     * @return ?string
     */
    private static function decodeValue(string $value): ?string {
        foreach (Config::get('decoders') as $decoder) {
            if (is_callable($decoder) && $decoder($value) !== null) {
                return $decoder($value);
            }
        }

        return null;
    }

    /**
     * Format value.
     *
     * @param string $value
     *
     * @return ?string
     */
    private static function formatValue(string $value): ?string {
        foreach (Config::get('formatters') as $formatter) {
            if (is_callable($formatter) && $formatter($value) !== null) {
                return $formatter($value);
            }
        }

        return null;
    }

    /**
     * Format JSON.
     *
     * @param string $value
     *
     * @return string
     */
    private static function formatJson(string $value): string {
        try {
            $json_array = json_decode($value, false, 512, JSON_THROW_ON_ERROR);

            if (!is_numeric($value) && $json_array !== null) {
                $value = json_encode($json_array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

                return '<pre>'.htmlspecialchars($value).'</pre>';
            }
        } catch (Exception $e) {
            return htmlspecialchars($value);
        }

        return htmlspecialchars($value);
    }
}
