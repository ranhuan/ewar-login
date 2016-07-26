<?php
require $_SERVER['INIT_SCRIPT'];

$fb_user_id = trim(_get('fb_user_id', ''));
$fb_access_token = trim(_get('fb_access_token', ''));

//code
//state

set_include_path(get_include_path() . PATH_SEPARATOR . LIB_PATH . 'facebook-php-sdk/src');
require LIB_PATH . 'facebook-php-sdk/src/Facebook/autoload.php';
$fb = new Facebook\Facebook([
	'app_id' => '891401940969295', // Replace {app-id} with your app id
	'app_secret' => '73a9147c91657731cd8cc99dd428cc80',
	'default_graph_version' => 'v2.2',
]);
$helper = $fb->getRedirectLoginHelper();

if (strlen($fb_user_id) < 2) {
	//redirect($helper->getLoginUrl('http://login.mmoup.cn/facebook/login', []));
	exit(json_encode(['code' => 412, 'message' => 'user id is required.']));
}
if (strlen($fb_access_token) < 2) {
	//redirect($helper->getLoginUrl('http://login.mmoup.cn/facebook/login', []));
	exit(json_encode(['code' => 412, 'message' => 'access token is required.']));
}

try {
	// Returns a `Facebook\FacebookResponse` object
	$response = $fb->get('/me?fields=id,name', $fb_access_token);
} catch (Facebook\Exceptions\FacebookResponseException $e) {
	echo 'Graph returned an error: ' . $e->getMessage();
	exit;
} catch (Facebook\Exceptions\FacebookSDKException $e) {
	echo 'Facebook SDK returned an error: ' . $e->getMessage();
	exit;
}

$user = $response->getGraphUser();
$fb_id = $user->getId();

$user = $db->from('user')->where('fb_id=?', $fb_id)->select()->fetch();
if (false == $user) {
//创建
	$row = [
		'origin' => 'facebook',
		'fb_id' => $fb_id,
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

//获取服务器列表
$ch = curl_init($config['etcd']['gateway'] . $config['etcd']['services']['gate']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$etcd_resp = json_decode(curl_exec($ch), TRUE);

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

exit(json_encode(['code' => 101, 'message' => 'Server is down for maintenance']));