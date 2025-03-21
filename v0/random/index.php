<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure the request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Only GET method is allowed"]);
    exit;
}

require_once '../config.php';

$core = 'jobs';

try {
    // Request the total number of documents
    $url = 'http://' . $server . '/solr/' . $core . '/select?q=' . urlencode('*:*') . '&rows=0';
    $string = @file_get_contents($url);
    if ($string === FALSE) {
        http_response_code(503);
        echo json_encode([
            "error" => "SOLR server in DEV is down",
            "code" => 503
        ]);
        exit;
    }
    $json = json_decode($string, true);
    $max = $json['response']['numFound'];

    // If no documents are found, return a specific message
    if ($max == 0) {
        echo json_encode(['message' => 'There are no jobs to display']);
        exit;
    }

    // Randomly select a document if there are jobs
    $start = rand(0, $max - 1);

    $url = 'http://' . $server . '/solr/' . $core . '/select?q=' . urlencode('*:*') . '&rows=1' . '&start=' . $start . '&omitHeader=true';
    $json = @file_get_contents($url);
    if ($json === FALSE) {
        list($version, $status, $msg) = explode(' ', $http_response_header[0], 3);
        // Force HTTP status code to be 503
        header("HTTP/1.1 503 Service Unavailable");
        throw new Exception('Your call to Solr failed and returned HTTP status: ' . $status, $status);
    }

    $jsonArray = json_decode($json, true);
    unset($jsonArray['response']['docs'][0]['_version_']);
    $newJson = json_encode($jsonArray, JSON_PRETTY_PRINT);
    echo $newJson;
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'code' => $e->getCode()]);
    exit;
}
