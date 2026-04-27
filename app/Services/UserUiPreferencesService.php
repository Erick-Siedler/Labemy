<?php

namespace App\Services;

class UserUiPreferencesService
{
    public function resolveTheme($userPreferences): string
    {
        if (is_array($userPreferences)) {
            return $this->formatThemeValue($userPreferences['theme'] ?? 'light');
        }

        if (is_object($userPreferences)) {
            $asArray = (array) $userPreferences;
            if (array_key_exists('theme', $asArray)) {
                return $this->formatThemeValue($asArray['theme']);
            }
        }

        if (is_string($userPreferences) && trim($userPreferences) !== '') {
            $decoded = json_decode($userPreferences, true);
            if (is_array($decoded) && array_key_exists('theme', $decoded)) {
                return $this->formatThemeValue($decoded['theme']);
            }

            $trimmed = trim($userPreferences, " \t\n\r\0\x0B\"'");
            if ($trimmed !== '') {
                return $this->formatThemeValue($trimmed);
            }
        }

        $encoded = json_encode($userPreferences, true);
        if (is_string($encoded) && $encoded !== '' && $encoded !== 'null') {
            $decoded = json_decode($encoded, true);
            if (is_array($decoded) && array_key_exists('theme', $decoded)) {
                return $this->formatThemeValue($decoded['theme']);
            }
        }

        return '"light"';
    }

    private function formatThemeValue($value): string
    {
        $theme = trim((string) ($value ?? 'light'), "\"' ");
        if ($theme === '') {
            $theme = 'light';
        }

        return '"' . $theme . '"';
    }
}
