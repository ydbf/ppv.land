<?php

define('API_BASE_URL', 'https://ppv.land/api/streams');
define('FILE_EXTENSION', '-ppv.land.m3u');

function fetchApiData($url) {
    $response = file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}

function getStreamData($id) {
    $url = API_BASE_URL . "/$id";
    $data = fetchApiData($url);
    if (!$data) {
        logError("Failed to fetch or decode data for stream ID: $id");
    }
    return $data;
}

function logError($message) {
    error_log($message . PHP_EOL, 3, 'errors.log');
}

function isValidEvent($startTimestamp, $category) {
    $currentTime = time();
    $timeDiff = $startTimestamp - $currentTime;
    return ($category === "24/7 Streams" || ($timeDiff >= -14400 && $timeDiff <= 172800));
}

function createM3UEntry($data, $category) {
    $name = $data['name'];
    $poster = $data['poster'];
    $m3u8Link = $data['m3u8'];
    $startTime = date('h:i A', $data['start_timestamp']) . ' (' . date('d/m/y', $data['start_timestamp']) . ')';

    // Define the VLC options to be included
    $vlcOptions = [
        '#EXTVLCOPT:http-referrer=https://ppv.land/',
        '#EXTVLCOPT:http-origin=https://ppv.land/',
        '#EXTVLCOPT:http-user-agent=Mozilla/5.0 (iPhone; CPU iPhone OS 17_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
    ];

    // Combine VLC options into a single string with line breaks
    $vlcOptionsString = implode("\n", $vlcOptions) . "\n";

    // Construct the M3U entry based on category
    $infoLine = ($category === "24/7 Streams") 
        ? "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name\n" 
        : "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name - $startTime\n";

    // Return the M3U entry with the VLC options inserted
    return $infoLine . $vlcOptionsString . $m3u8Link . "\n";
}

function main() {
    $data = fetchApiData(API_BASE_URL);
    if (!$data || !isset($data['streams'])) {
        logError("Failed to fetch or decode main API data.");
        return;
    }

    $ids = [];
    $categoryMap = [];
    foreach ($data['streams'] as $categoryGroup) {
        $category = $categoryGroup['category'];
        foreach ($categoryGroup['streams'] as $stream) {
            if (isset($stream['id']) && strlen((string)$stream['id']) === 4) {
                $ids[] = $stream['id'];
                $categoryMap[$stream['id']] = $category;
            }
        }
    }

    $linkInfo = [];
    foreach ($ids as $id) {
        $streamData = getStreamData($id);
        if ($streamData) {
            $linkInfo[] = $streamData;
        }
    }

    $timezones = [
        'Australia/Sydney',
        'Iceland',
        'America/New_York',
        'America/Los_Angeles',
        'America/Chicago',
    ];

    foreach ($timezones as $timezone) {
        date_default_timezone_set($timezone);
        $fileName = str_replace('/', '-', $timezone) . FILE_EXTENSION;
        $m3uFile = fopen($fileName, 'w');
        if (!$m3uFile) {
            logError("Error opening file $fileName for writing.");
            continue;
        }

        fwrite($m3uFile, "#EXTM3U\n");

        foreach ($linkInfo as $entry) {
            $data = $entry['data'];
            $id = $data['id'];
            $category = $categoryMap[$id] ?? 'Uncategorized';

            if (isValidEvent($data['start_timestamp'], $category)) {
                fwrite($m3uFile, createM3UEntry($data, $category));
            }
        }

        fclose($m3uFile);

        echo "M3U file created successfully as $fileName.\n";
    }
}

main();
?>
