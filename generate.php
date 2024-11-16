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

date_default_timezone_set('Australia/Sydney');

$m3uFile = fopen('streams.m3u', 'w') or die('Error opening M3U file for writing.');

fwrite($m3uFile, "#EXTM3U\n");

$userAgent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15";
$origin = "https://ppv.land";
$referrer = "https://ppv.land";

foreach ($linkInfo as $entry) {
    $data = $entry['data'];
    $name = $data['name'];
    $poster = $data['poster'];
    $m3u8Link = $data['m3u8'];
    $id = $data['id'];

    $category = $categoryMap[$id] ?? 'Uncategorized';
    $startTime = date('h:i A', $data['start_timestamp']) . ' (' . date('d/m/y', $data['start_timestamp']) . ')';

    $m3uEntry = "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name - $startTime\n";
    $m3uEntry .= "#EXTVLCOPT:http-user-agent=$userAgent\n";
    $m3uEntry .= "#EXTVLCOPT:http-origin=$origin\n";
    $m3uEntry .= "#EXTVLCOPT:http-referrer=$referrer\n";
    $m3uEntry .= "$m3u8Link\n";

    fwrite($m3uFile, $m3uEntry);
}

fclose($m3uFile);

echo "M3U file created successfully as streams.m3u.\n";

?>
