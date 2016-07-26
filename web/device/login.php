<?php
require $_SERVER['INIT_SCRIPT'];

$device_id = trim(_get('device_id', ''));
if (strlen($device_id) < 2) {
	exit(json_encode(['code' => 412, 'message' => '机器码格式错误']));
}

//查找
$user = $db->from('user')->where('device_id=?', $device_id)->select()->fetch();
if (false === $user) {
//创建
	$row = [
		'origin' => 'local',
		'device_id' => $device_id,
		'joined_time' => time(),
	];
	if (1 != $db->insert('user', $row)) {
		exit(json_encode(['code' => 500, 'message' => '创建用户失败']));
	}

	$user_id = $db->lastInsertId();
	$user = $db->from('user')->where('id=?', $user_id)->select()->fetch();
}

$bind_google = strlen($user['gg_id']) > 0 ? true : false;
$bind_facebook = strlen($user['fb_id']) > 0 ? true : false;

/*
//判断ip来源
use GeoIp2\Database\Reader;

$client_country = 'SG';

try {
$reader = new Reader('/usr/local/share/GeoIP/GeoIP2-Country.mmdb');
$record = $reader->country(REMOTE_ADDR);
$client_country = $record->country->isoCode;
} catch (Exception $e) {
glog($e->getMessage());
header("CountryError: " . $e->getMessage());
//echo $e->getMessage();
}

header("Country: $client_country");
 */

$ch = curl_init($config['etcd']['gateway'] . $config['etcd']['services']['gate']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$response = curl_exec($ch);
if (curl_errno($ch) > 0) {
	resp([
		'code' => 101,
		'message' => curl_error($ch),
	]);
}

$etcd_resp = json_decode($response, TRUE);
if (!is_array($etcd_resp)) {
	resp([
		'code' => 101,
		'message' => 'json decode failed',
	]);
}

if (isset($etcd_resp['node']['nodes']) && count($etcd_resp['node']['nodes'])) {
	$gate_servers = [];

	foreach ($etcd_resp['node']['nodes'] as $node) {
		$node_key = substr($node['key'], strrpos($node['key'], '/') + 1);
		$server_host = substr($node_key, 0, strpos($node_key, ':'));
		$server_country = 'Unknown';
		if (substr($server_host, 0, 8) == '47.88.19') {
			$server_country = 'SG';
		} else if (substr($server_host, 0, 2) == '12') {
			$server_country = 'CN';
		}
		$gate_servers[] = [
			'addr' => $node_key,
			'host' => $server_host,
			'country' => $server_country,
			'users' => $node['value'],
		];
	}

	$_gate_servers = [];
	foreach ($gate_servers as $gate_server) {
		$_gate_servers[$gate_server['addr']] = $gate_server['users'];
	}
	asort($_gate_servers, SORT_NUMERIC);

	//查找同地区的服务器
	/*
	$_gate_servers = [];
	foreach ($gate_servers as $gate_server) {
		if ($gate_server['country'] == $client_country) {
			$_gate_servers[] = $gate_server['addr'];
		}
	}
	foreach ($gate_servers as $gate_server) {
		if ($gate_server['country'] != $client_country) {
			$_gate_servers[] = $gate_server['addr'];
		}
	}*/

//生成令牌
	$token = uuid_create();

//写入cache
	//memcache
	if ($config['uuid_storage']['memcache']) {
		$mc = new Memcached();
		$mc->addServer($config['memcache']['host'], $config['memcache']['port']);
		$mc->set($token, $user['id'], $config['memcache']['expiration']);
	}

	//redis
	if ($config['uuid_storage']['redis']) {
		$redis = new Redis();
		$redis->pconnect($config['redis']['host'], $config['redis']['port']);
		$redis->auth($config['redis']['pass']);
		$redis->select($config['redis']['db']);
		$redis->set($token, $user['id'], $config['redis']['ttl']);
	}
	//list($host, $port) = explode(':', $gate_servers[0]);
	$mc->set('gate_servers', $gate_servers, 10);

	resp([
		'code' => 200,
		'message' => 'success',
		'token' => $token, /*'host' => $host, 'port' => (int) $port, */
		'servers' => array_keys($_gate_servers),
		'bind_google' => $bind_google,
		'bind_facebook' => $bind_facebook,
	]);
}

resp([
	'code' => 101,
	'message' => 'Server is down for maintenance',
]);