<?php
require $_SERVER['INIT_SCRIPT'];

//init php sdk
set_include_path(get_include_path() . PATH_SEPARATOR . LIB_PATH . 'google-api-php-client/src');
require LIB_PATH . 'google-api-php-client/src/Google/autoload.php';
$client = new Google_Client();
$client->setAuthConfigFile(CONF_PATH . $config['google']['auth_cfg']);
$client->addScope([Google_Service_Oauth2::USERINFO_PROFILE, Google_Service_Oauth2::USERINFO_EMAIL]);

//google auth code
$auth_code = trim(_get('gg_auth_code', _get('code', '')));
if (strlen($auth_code) < 2) {
	//redirect($client->createAuthUrl());
	resp(['code' => 412, 'message' => 'Auth-Code is Required.']);
}

try {
	//proxy
	if ($config['google']['proxy']) {
		$io = new Google_IO_Curl($client);
		$io->setOptions([CURLOPT_PROXY => $config['proxy'], CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5]);
		$client->setIo($io);
	}

	//auth
	glog(var_export($client->authenticate($auth_code), true));

	//parse id token
	$token_data = $client->verifyIdToken();

	//查询数据库是否存在该google用户
	$user = $db->from('user')->where('gg_id=?', $token_data->getUserId())->select()->fetch();
	if (false === $user) {
		//创建
		$row = [
			'origin' => 'google',
			'gg_id' => $token_data->getUserId(),
			'joined_time' => time(),
		];
		if (1 == $db->insert('user', $row)) {
			$user = $db->from('user')->where('id=?', $db->lastInsertId())->select()->fetch();
		} else {
			resp(['code' => 500, 'message' => '创建用户失败']);
		}
	}

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
} catch (Exception $e) {
	resp(['code' => $e->getCode(), 'message' => $e->getMessage()]);
}

exit(json_encode(['code' => 101, 'message' => 'Server is down for maintenance']));