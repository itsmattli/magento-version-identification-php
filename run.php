<?php

if (empty($argv[1])) {
    echo "please run as php -f run.php filename.csv";
}

if(!file_exists($argv[1])) {
    echo "file specified doesn't exist";
}

$default_socket_timeout = ini_get('default_socket_timeout');
set_time_limit(0);

ini_set('default_socket_timeout', 3);

$csv = array_map('str_getcsv', file($argv[1]));
$data = init($csv);
var_dump($data);
writeCSV($data);

ini_set('default_socket_timeout', $default_socket_timeout);
function init($csv) {
    $entries = count($csv);
    $data = [];
    $count = 0;
    foreach ($csv as $site) {
        $startTime = time();
        if (empty($site[0])) {
            continue;
        }

        $urlHttp = 'http://' . $site[0];
        $urlHttps = 'https://' . $site[0];

        $output = mage2test($urlHttp);
        if ($output) {
            $outputArray = array_merge([$urlHttp], formatMage2Output($output));
            $count++;
            printStats($outputArray, $startTime, $count, $entries);
            $data []= $outputArray;
            continue;
        }

        $output = mage2test($urlHttps);
        if ($output) {
            $outputArray = array_merge([$urlHttps], formatMage2Output($output));
            $count++;
            printStats($outputArray, $startTime, $count, $entries);
            $data []= $outputArray;
            continue;
        }

        $output = mage1test($urlHttp);
        if ($output) {
            $outputArray = array_merge([$urlHttp], formatMage1Output($output));
            $count++;
            printStats($outputArray, $startTime, $count, $entries);
            $data []= $outputArray;
            continue;
        }

        $output = mage1test($urlHttps);
        if ($output) {
            $outputArray = array_merge([$urlHttps], formatMage1Output($output));
            $count++;
            printStats($outputArray, $startTime, $count, $entries);
            $data []= $outputArray;
            continue;
        }

        if ($output === NULL) {
            $count++;
            printStats([$site[0], 'Not Found', 'Not Found'], $startTime, $count, $entries);
            $data []= [$site[0], 'Not Found', 'Not Found'];
        }
    }
    return $data;
}

function mage2test($url)
{
    if (get_http_response_code($url . '/magento_version') == "200") {
        $output = file_get_contents($url . '/magento_version');
        if (strlen($output) < 200) {
            if (strpos($output, 'Magento') !== false) {
                return $output;
            }
        }
    } 
    return null;
}

function mage1test($url)
{
    $output = shell_exec('./bin/mvi check ' . $url . '/');
    if (strpos($output, 'Version') !== false) {
        return $output;
    }
    return null;
}

function get_http_response_code($url)
{
    $headers = @get_headers($url);
    if (!$headers) {
        return null;
    }
    return substr($headers[0], 9, 3);
}

function formatMage2Output($output) {
    $output = explode('Magento/', $output);
    $output = explode(' ', $output[1]);
    $output[1] = str_replace(['(',')'], '', $output[1]);
    return $output;
}

function formatMage1Output($output) {
    $output = str_replace(' ', '', $output);
    $output = explode('Edition:', $output);
    $outputArray = explode('Version:', $output[1]);
    return [
        trim(str_replace(',', ' - ', $outputArray[1])),
        $outputArray[0]
    ];
}

function printStats($output, $startTime, $count, $maxCount) {
    print_r($output);
    echo PHP_EOL . 'Took ' . (time() - $startTime) . ' seconds.' . PHP_EOL;
    echo $count . ' of ' . $maxCount . PHP_EOL;
}

function writeCSV($data) {
    echo 'writing to output.csv' . PHP_EOL;
    $fp = fopen('output.csv', 'w');
    fputcsv($fp, ['site','version','edition']);
    foreach ($data as $fields) {
        fputcsv($fp, $fields);
    }
    fclose($fp);
}
