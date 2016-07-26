<?php
class MySQL {
	protected static $_instance = null;
	public $debug = false;
	private $_pdo = null;
	private $_pdo_statement = null;
	//select
	private $_from = array();
	private $_where = array();
	private $_order = array();
	private $_limit = array();
	private $_join = array();
	private $_for_update = false;
	private $_group_by = array();

	static function getInstance($config = null) {
		if (null === self::$_instance) {
			self::$_instance = new self($config);
		}

		return self::$_instance;
	}

	public function __construct($config) {
		$dsn = $config['driver'] . ':';
		if (isset($config['sock'])) {
			$dsn .= 'unix_socket=' . $config['sock'];
		} else {
			$dsn .= 'host=' . $config['host'] . ';port=' . $config['port'];
		}

		$dsn .= ';dbname=' . $config['dbname'] . ';charset=utf8';
		$driver_options = array(
			PDO::ATTR_TIMEOUT => 2,
			PDO::ATTR_PERSISTENT => true,
			//PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;SET time_zone = "Asia/Shanghai";',
		);
		$this->_pdo = new PDO($dsn, $config['user'], $config['pass'], $driver_options);
	}

	function __get($name) {
		if ('pdo' == $name) {
			return $this->_pdo;
		} else {
			return false;
		}
	}

	function __set($name, $value) {
		if ('pdo' == $name) {
			$this->_pdo = $value;
		}
	}

	//pdo
	public function query($sql) {
		$this->_pdo_statement = $this->_pdo->query($sql);
		$this->checkError();
		return $this;
	}

	public function prepare($sql, $driver_options = array()) {
		$this->_pdo_statement = $this->_pdo->prepare($sql, $driver_options);
		$this->checkError();
		return $this;
	}

	public function exec($sql) {
		$rs = $this->_pdo->exec($sql);
		$this->checkError();
		return $rs;
	}

	public function beginTransaction() {
		return $this->_pdo->beginTransaction();
	}

	public function rollBack() {
		return $this->_pdo->rollBack();
	}

	public function commit() {
		return $this->_pdo->commit();
	}

	public function errorCode() {
		return $this->_pdo->errorCode();
	}

	public function errorInfo() {
		return $this->_pdo->errorInfo();
	}

	public function getAttribute($attribute) {
		return $this->_pdo->getAttribute($attribute);
	}

	public function getAvailableDrivers() {
		return $this->_pdo->getAvailableDrivers();
	}

	public function lastInsertId() {
		return $this->_pdo->lastInsertId();
	}

	public function quote($string, $parameter_type = PDO::PARAM_STR) {
		if (is_null($string)) {
			return 'NULL';
		}
		return htmlspecialchars($this->_pdo->quote($string, $parameter_type));
	}

	protected function quoteKey($key) {
		return '`' . $key . '`';
	}

	public function setAttribute($attribute, $value) {
		return $this->_pdo->setAttribute($attribute, $value);
	}

	//end pdo
	//pdo statement
	public function fetch($fetch_style = PDO::FETCH_ASSOC) {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return false;
		}
		$result = $this->_pdo_statement->fetch($fetch_style);
		return $result;
	}

	public function fetchPairs($fetch_style = PDO::FETCH_NUM) {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return array();
		}
		$result = $this->_pdo_statement->fetchAll($fetch_style);
		if (false == $result) {
			return array();
		}
		$tmp = array();
		foreach ($result as $res) {
			$tmp[$res[0]] = $res[1];
		}
		return $tmp;
	}

	public function fetchAll($fetch_style = PDO::FETCH_ASSOC) {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return array();
		}
		$result = $this->_pdo_statement->fetchAll($fetch_style);
		if (false == $result) {
			return array();
		}
		return $result;
	}

	public function fetchColumn($column_number = 0) {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return false;
		}
		$result = $this->_pdo_statement->fetchColumn($column_number);
		return $result;
	}

	public function rowCount() {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return 0;
		}
		return $this->_pdo_statement->rowCount();
	}

	public function execute($input_parameters = array()) {
		return $this->_pdo_statement->execute();
	}

	public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR) {
		if (!$this->_pdo_statement instanceof PDOStatement) {
			return false;
		}
		return $this->_pdo_statement->bindValue($parameter, $value, $data_type);
	}

	//end pdo statement
	//sql
	public function insert($table, $data) {
		//防止误操作
		if (!is_array($data)) {
			return false;
		}

		if ('' == $table) {
			return false;
		}

		$fields = array();

		$sql = 'INSERT INTO ' . $this->quoteKey($table)
		. ' (' . implode(', ', array_map(array($this, 'quoteKey'), array_keys($data))) . ')'
		. ' VALUES (' . implode(', ', array_map(array($this, 'quote'), array_values($data))) . ')';
		return $this->exec($sql);
	}

	public function replace($table, $data) {
		//防止误操作
		if (!is_array($data)) {
			return false;
		}

		if ('' == $table) {
			return false;
		}

		$fields = array();

		$sql = 'REPLACE INTO ' . $this->quoteKey($table)
		. ' (' . implode(', ', array_map(array($this, 'quoteKey'), array_keys($data))) . ')'
		. ' VALUES (' . implode(', ', array_map(array($this, 'quote'), array_values($data))) . ')';

		return $this->exec($sql);
	}

	public function delete($table, $where, $value = null) {
		//防止误操作
		if ($where == '') {
			return false;
		}

		if ('' == $table) {
			return false;
		}

		if (!is_null($value)) {
			$value = $this->quote($value);
			//if (!is_numeric($value)) $value = '"' . $value . '"';
			$where = str_replace('?', $value, $where);
		}

		if (!is_array($where)) {
			$where = array($where);
		}

		$sql = 'DELETE FROM ' . $this->quoteKey($table) . ' WHERE ' . implode(' AND ', $where);
		//Tuki_Debug::log($sql, 'db');
		return $this->exec($sql);
	}

	public function update($table, $data, $where, $value = null) {
		//防止误操作
		if ($where == '') {
			return false;
		}

		if (!is_array($data)) {
			return false;
		}

		if ('' == $table) {
			return false;
		}

		$fields = array();
		foreach ($data as $key => $val) {
			$fields[] = $key . '=' . $this->quote($val);
		}

		$dest = array();
		foreach ($data as $key => $val) {
			$dest[] = $this->quoteKey($key) . '=' . $this->quote($val);
		}

		if (is_null($value) && preg_match('/^[1-9][\d]*$/', $where)) {
			$value = $where;
			$where = 'id=?';
		}

		if (!is_null($value)) {
			//if (!is_numeric($value)) $value = '"' . $value . '"';
			if (is_array($value)) {
				$where = str_replace('=', ' IN ', $where);
				$where = str_replace('?', '(' . implode(',', $value) . ')', $where);
			} else {
				$where = str_replace('?', $this->quote($value), $where);
			}
		}

		if (!is_array($where)) {
			$where = array($where);
		}

		$sql = 'UPDATE ' . $this->quoteKey($table) . ' SET ' . implode(',', $dest) . ' WHERE ' . implode(' AND ', $where);

		if ($this->debug) {
			echo $sql;
		}

		return $this->exec($sql);
	}

	//select
	//from
	public function from($name, $cols = '*') {
		$alias = null;
		if (($pos = strpos(strtolower($name), ' as ')) === false) {
			$table_name_secs = explode('_', $name);
			$alias = '';
			foreach ($table_name_secs as $table_name_sec) {
				$alias .= substr($table_name_sec, 0, 1);
			}
			//$alias = substr($name, 0, 1);
		} else {
			$alias = substr($name, $pos + 4);
			$name = substr($name, 0, $pos);
		}
		if ('*' == $cols) {
			$cols = is_null($alias) ? $name . '.*' : $alias . '.*';
		}

		$this->_from = array('name' => $this->_fn($name), 'alias' => $alias, 'cols' => $this->_fn($cols, $alias));

		return $this;
	}

	//where
	public function where($cond, $value = null) {
		$this->_where[] = array('cond' => $cond, 'value' => $value);
		return $this;
	}

	//group
	public function group($field) {
		$this->_group_by[] = array('field' => $field);
		return $this;
	}

	//order
	public function order($spec, $is_desc = false) {
		if (strtolower(substr($spec, -5)) == ' desc') {
			$is_desc = true;
			$spec = substr($spec, 0, -5);
		} elseif (strtolower(substr($spec, -4)) == ' asc') {
			$is_desc = false;
			$spec = substr($spec, 0, -4);
		}
		$this->_order[] = array('spec' => $this->_fn($spec), 'is_desc' => $is_desc);
		return $this;
	}

	//for update
	public function forUpdate() {
		$this->_for_update = true;
		return $this;
	}

	//limit
	public function limit($count, $offset = 0) {
		$this->_limit = array('offset' => $offset, 'count' => $count);
		return $this;
	}

	public function limitPage($page, $page_size) {
		$page = ($page > 0) ? $page : 1;
		$page_size = ($page_size > 0) ? $page_size : 1;
		$this->limit($page_size, $page_size * ($page - 1));
		return $this;
	}

	public function joinLeft($name, $cond, $cols = '*') {
		$alias = null;
		if (($pos = strpos(strtolower($name), ' as ')) !== false) {
			$alias = substr($name, $pos + 4);
			$name = substr($name, 0, $pos);
		} else {
			$table_name_secs = explode('_', $name);
			$alias = '';
			foreach ($table_name_secs as $table_name_sec) {
				$alias .= substr($table_name_sec, 0, 1);
			}
		}
		if (is_array($cols)) {

		}
		if ($cols == '*') {
			$cols = is_null($alias) ? $name . '.*' : $alias . '.*';
		}

		$this->_join[] = array('type' => 'LEFT JOIN', 'name' => $name, 'alias' => $alias, 'cond' => $cond, 'cols' => $this->_fn($cols, $alias));
		return $this;
	}

	public function select($debug = false) {
		//from
		$sql = 'SELECT ';
		$sql .= is_array($this->_from['cols']) ? implode(', ', $this->_from['cols']) : $this->_from['cols'];
		if (count($this->_join)) {
			foreach ($this->_join as $i => &$join) {
				$sql .= (0 == $i && empty($this->_from['cols'])) || empty($join['cols']) ? '' : ', ';
				$sql .= is_array($join['cols']) ? implode(', ', $join['cols']) : (empty($join['cols']) ? '' : $join['cols']);
			}
		}
		$sql .= ' FROM ' . $this->_from['name'];
		if (!is_null($this->_from['alias'])) {
			$sql .= ' AS `' . $this->_from['alias'] . '`';
		}

		//join
		if (count($this->_join)) {
			foreach ($this->_join as &$join) {
				$sql .= ' ' . $join['type'] . ' ' . $join['name'];
				if (!is_null($join['alias'])) {
					$sql .= ' AS `' . $join['alias'] . '`';
				}

				$sql .= ' ON ' . $join['cond'];
			}
		}

		//where
		if (count($this->_where)) {
			$where_key = ' WHERE ';
			foreach ($this->_where as &$where) {
				if (!is_null($where['value'])) {
					//format cond
					$where['cond'] = preg_replace('/([a-z][a-z0-9_]*)/', '`$1`', $where['cond']);
					if (is_array($where['value'])) {
						//$where['value'] = array_map([$this, 'quote'], $where['value']);
						//if (preg_match('/ IN /', $where['cond'])) {
						//    $where['value'] = implode(', ', $where['value']);
						//} else {
						$cond = '(';
						foreach ($where['value'] as $idx => $value) {
							$value = $this->quote($value);
							if ($idx) {
								$cond .= ' OR ';
							}

							//if (!is_numeric($value)) $value = '"' . $value . '"';
							//$value = '"' . $value . '"';
							$cond .= $where['cond'];
							$cond = str_replace('?', $value, $cond);
						}
						$cond .= ')';
						$where['cond'] = $cond;
						//}
					} else {
						$where['value'] = $this->quote($where['value']);
						//if (!is_numeric($where['value'])) $where['value'] = '"' . $where['value'] . '"';
						//$where['value'] = '"' . $where['value'] . '"';
						$where['cond'] = str_replace('?', $where['value'], $where['cond']);
					}
				}
				$sql .= $where_key . $where['cond'];
				$where_key = ' AND ';
			}
		}

		if (count($this->_group_by)) {
			$group_by_key = ' GROUP BY';
			foreach ($this->_group_by as &$_group_by) {
				$sql .= $group_by_key . ' ' . $_group_by['field'];
				$group_by_key = ', ';
			}
		}

		//order
		if (count($this->_order)) {
			$order_key = ' ORDER BY';
			foreach ($this->_order as &$order) {
				$sql .= $order_key . ' ' . $order['spec'];
				$sql .= $order['is_desc'] ? ' DESC' : ' ASC';
				$order_key = ', ';
			}
		}

		//limit
		if (count($this->_limit)) {
			$sql .= ' LIMIT ' . $this->_limit['offset'] . ', ' . $this->_limit['count'];
		}

		if ($this->_for_update) {
			$sql .= ' FOR UPDATE';
		}

		//Tuki_Debug::log($sql);
		if ($debug) {
			echo $sql;
		}

		$this->reset();

		return $this->query($sql);
	}

	public function reset($parts = null) {
		if (is_null($parts)) {
			$this->_from = array();
			$this->_where = array();
			$this->_order = array();
			$this->_limit = array();
			$this->_join = array();
			$this->_for_update = false;
			$this->_group_by = array();
		} elseif (is_array($parts)) {
			foreach ($parts as $part) {
				$part = '_' . $part;
				$this->$part = array();
			}
		} else {
			$part = '_' . $parts;
			$this->$part = array();
		}

		return $this;
	}

	//format name
	private function _fn($name, $alias = null) {
		if (is_array($name)) {
			//如果为空直接返回
			if (0 == count($name)) {
				return '';
			}

			return array_map(array($this, '_fn'), $name, array_fill(0, count($name), $alias));
		} else {
			//如果为空直接返回
			if ($name == '') {
				return $name;
			}

			//如果调用了mysql函数直接返回
			if (false !== strpos($name, '(')) {
				return $name;
			}

			//如果指定了alias
			if (!is_null($alias) && strpos($name, '.') === false) {
				$name = $alias . '.' . $name;
			}
			if ($name == '*') {
				return '*';
			}

			if (substr($name, -1) == '*') {
				return '`' . str_replace('.', '`.', $name);
			}

			return '`' . str_replace('.', '`.`', str_replace(array(' AS ', ' as '), '` AS `', $name)) . '`';
		}
	}

	private function _addAlias(&$value, $key, $alias) {
		if (strpos($value, '.') === false) {
			$value = $alias . '.' . $value;
		}
	}

	public function checkError() {
		if ($this->_pdo->errorCode() > PDO::ERR_NONE) {
			$error_info = $this->_pdo->errorInfo();
			$debug_backtrace = var_export(debug_backtrace(), true);
			throw new Exception('SQLSTATE: ' . $error_info[0] . ' ' . $error_info[2], $error_info[1] >= 0 ? 0 - $error_info[1] : $error_info[1]);
		}
	}

	public function lockRow($table, $id) {
		return $this->from($table)->where('id=?', $id)->forUpdate()->select()->fetch();
	}

	public function lockRows($table, $condition, $val) {
		return $this->from($table)->where($condition, $val)->forUpdate()->select()->fetchAll();
	}
}

//end file