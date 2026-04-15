<?php

namespace App\Service;

/**
 * Parses User-Agent header to identify the device/browser.
 */
class DeviceDetectorService
{
    /**
     * Detect browser and OS from User-Agent string.
     *
     * @return string Human-readable device description (e.g. "Chrome Windows")
     */
    public function detect(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        $browser = $this->detectBrowser($userAgent);
        $os = $this->detectOS($userAgent);

        return trim($browser . ' ' . $os) ?: 'Unknown Device';
    }

    private function detectBrowser(string $ua): string
    {
        $browsers = [
            'Edg/'       => 'Edge',
            'OPR/'       => 'Opera',
            'Opera'      => 'Opera',
            'Chrome/'    => 'Chrome',
            'Firefox/'   => 'Firefox',
            'Safari/'    => 'Safari',
            'MSIE '      => 'IE',
            'Trident/'   => 'IE',
        ];

        foreach ($browsers as $pattern => $name) {
            if (stripos($ua, $pattern) !== false) {
                // Safari check: Chrome also contains "Safari" in UA
                if ($name === 'Safari' && stripos($ua, 'Chrome/') !== false) {
                    continue;
                }
                return $name;
            }
        }

        return 'Browser';
    }

    private function detectOS(string $ua): string
    {
        $osList = [
            'Windows NT 10'  => 'Windows',
            'Windows NT 6.3' => 'Windows',
            'Windows NT 6.1' => 'Windows',
            'Windows'        => 'Windows',
            'Android'        => 'Android',
            'iPhone'         => 'iOS',
            'iPad'           => 'iPadOS',
            'Macintosh'      => 'macOS',
            'Mac OS X'       => 'macOS',
            'Linux'          => 'Linux',
            'CrOS'           => 'ChromeOS',
        ];

        foreach ($osList as $pattern => $name) {
            if (stripos($ua, $pattern) !== false) {
                return $name;
            }
        }

        return '';
    }
}
