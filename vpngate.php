<?php
/**
 * Super generic PHP script for connecting to a vpngate.net VPN.
 *
 * Must be run as root.
 */
if (PHP_OS != "Linux") {
    exit("Only Linux is supported!");
}
$options = getopt("c:s", ['country::', 'status::']);
$country = $options['c'] ?? $options['country'] ?? null;
$checkStatus = isset($options['status']) || isset($options['s']);
$start = true;

if ($checkStatus) {
    if (isVpnRunning()) {
        $kill = readline("VPN is running. Kill it? [y/yes/n/no] ");
        if (in_array($kill, ['y', 'yes'])) {
            killVpn();
            $start = readline("VPN is not running. Start one? [y/yes/n/no]: ");
        }
    } else {
        $start = readline("VPN is not running. Start one? [y/yes/n/no]: ");
    }
}

if ($start === true || in_array($start, ['y', 'yes'])) {
    $response = explode("\n", file_get_contents('http://www.vpngate.net/api/iphone/'));
    $count = 0;
    foreach ($response as $row) {
        if ($count < 2) { // first two rows
            $count++;
            continue;
        }
        $connection = str_getcsv($row);
        if (!isset($connection[6])) {
            continue;
        }

        if (is_null($country) || $connection[6] == strtoupper($country)) {
            connectVPN($connection);
        }
    }
}

function isVpnRunning()
{
    exec("ps -aux | grep -i 'sudo openvpn --config' | grep -v grep | awk '{ print $2 }' | head -1", $pid);
    return !empty($pid);
}

function killVpn()
{
    exec('sudo killall openvpn');
}

function connectVPN($connection)
{
    echo 'Attempting to connect to ' . $connection[6] . ' - ' . $connection[1] . '...' . PHP_EOL;
    $configFile = '/tmp/' . uniqid($connection[6] . '_') . '.ovpn';
    file_put_contents($configFile, base64_decode($connection[14]));
    exec('sudo openvpn --config ' . $configFile . ' > /tmp/vpn.log &');

    // Sleep a few seconds and compare current IP to the one from the VPN server
    sleep(15);
    $ip = file_get_contents('http://icanhazip.com');


    if ($ip != $connection[1]) {
        echo "VPN did not connect to ' . $connection[6] . ' - ' . $connection[1] . ', moving to the next one..." . PHP_EOL;
        killVpn();
    } else {
        echo "Connected to " . $connection[6] . ' - ' . $connection[1] . ' successfully.' . PHP_EOL;
        exit();
    }
}
