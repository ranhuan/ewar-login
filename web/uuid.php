<?php
$config = [
	'memcache' => [
		'host' => '127.0.0.1',
		'port' => 11211,
	],
];

if (isset($_GET['uuid']) /* && 52 == strlen(trim($_GET['uuid'])*/) {
	$mc = new Memcached();
	$mc->addServer($config['memcache']['host'], $config['memcache']['port']);
	$uid = $mc->get(trim($_GET['uuid']));
	if ($uid > 0) {
		exit((string) $uid);
	}
}

exit('0');
