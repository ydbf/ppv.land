<?php

$url = "https://ppv.land/api/streams";
$response = file_get_contents($url);
$data = json_decode($response, true);

$ids = [];
$categoryMap = [];

foreach ($data['streams'] as $categoryGroup) {
    foreach ($categoryGroup['streams'] as $stream) {
        if (isset($stream['id']) && strlen((string)$stream['id']) === 4) {
            $ids[] = $stream['id'];
            $categoryMap[$stream['id']] = $categoryGroup['category'];
        }
    }
}

$linkInfo = [];
foreach ($ids as $id) {
    $jsonData = file_get_contents("https://ppv.land/api/streams/$id");

    if ($jsonData === false) {
        echo "Failed to fetch data for stream ID: $id\n";
        continue;
    }

    $streamData = json_decode($jsonData, true);
    if ($streamData === null) {
        echo "Invalid JSON data for stream ID: $id\n";
        continue;
    }

    $linkInfo[] = $streamData;
}

$timezones = [
    'Australia/Sydney',
    'Iceland',
    'America/New_York',
    'America/Los_Angeles',
    'America/Chicago'
];

function isValidEvent($startTimestamp, $category) {
    $currentTime = time();
    $timeDiff = $startTimestamp - $currentTime;

    if ($category !== "24/7 Streams") {
        if ($timeDiff < -14400 || $timeDiff > 172800) {
            return false;
        }
    }
    return true;
}

foreach ($timezones as $timezone) {
    date_default_timezone_set($timezone);

    $fileName = str_replace('/', '-', $timezone) . "-ppv.land.m3u";
    $m3uFile = fopen($fileName, 'w') or die("Error opening file $fileName for writing.");

    fwrite($m3uFile, "#EXTM3U\n");

    foreach ($linkInfo as $entry) {
        $data = $entry['data'];
        $name = $data['name'];
        $poster = $data['poster'];
        $m3u8Link = $data['m3u8'];
        $id = $data['id'];
        $category = $categoryMap[$id] ?? 'Uncategorized';
        $startTime = date('h:i A', $data['start_timestamp']) . ' (' . date('d/m/y', $data['start_timestamp']) . ')';

        if (!isValidEvent($data['start_timestamp'], $category)) {
            continue;
        }

        if ($category === "24/7 Streams") {
            $m3uEntry = "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name\n";
        } else {
            $m3uEntry = "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name - $startTime\n";
        }

        $m3uEntry .= "$m3u8Link\n";

        fwrite($m3uFile, $m3uEntry);
    }

    fclose($m3uFile);

    echo "M3U file created successfully as $fileName.\n";
}
