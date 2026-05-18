<?php
namespace Lukiman\Cores\Database\Query;

use \Lukiman\Cores\Database;
use \Lukiman\Cores\Database\Query as Database_Query;

class Select extends Database_Query {
	protected mixed $_dbStatement = null;
	protected null|String|array $_join = array();
	protected String $_orderBy = '';
	protected array|String $_groupBy = [];
	protected String $_useHaving = '';
	protected null|int|array $_useLimit = null;
	protected int $_rowCount = 0;

	public function execute(?Database $db = null) : self {
		parent::execute($db);
		// if (is_null($db)) $db = $this->_db;
		$db = $this->getValidDb($db);
		if (is_array($this->_orderBy)) $this->_orderBy = implode(' , ', $this->_orderBy);
		if (is_array($this->_groupBy)) $this->_groupBy = implode(' , ', $this->_groupBy);
		if (is_array($this->_useHaving)) $this->_useHaving = implode(' , ', $this->_useHaving);

		if (is_array($this->_join)) $this->_join = implode(' ', $this->_join);
		// Database::activate($setting);
		$this->_dbStatement = Database::Select($db, $this->_table, $this->_columns, $this->_where, $this->_bindVars, $this->_join, $this->_orderBy, $this->_groupBy, $this->_useHaving, $this->_useLimit);
		$this->_rowCount = $this->_dbStatement->rowCount();
		return $this;
	}

	public function count() : int {
		// return $this->_dbStatement->rowCount();
		return $this->_rowCount;
	}

	public function next(String $type = 'default') : mixed {
		if (empty($this->_dbStatement)) $this->execute();
		$row = $this->_dbStatement->fetch();
		if ($type == 'array') $row = (array) $row;
		return $row;
	}

	public function leftJoin (String $join, array|String $on) : self {
		return $this->join($join, $on, 'left');
	}

	public function rightJoin (String $join, array|String $on) : self {
		return $this->join($join, $on, 'right');
	}

	public function join(String $join, array|String $on, String $type = '') : self {
		$__join = ' JOIN ';
		$_on = $on;
		if (is_array($on)) $_on = implode (' AND ', $on);
		if ($type == 'left') $__join = ' LEFT ' . $__join;
		else if ($type == 'right') $__join = ' RIGHT ' . $__join;
		$this->_join[] = $__join . $join . ' ON ( ' . $_on . ' ) ';
		return $this;
	}

	public function sort(array|String $order, ?String $type = null) : self {
		return $this->order($order, $type);
	}

	public function order(array|String $order, ?String $type = null) : self {
		$validType = array('ASC', 'DESC');
		if (!in_array(strtoupper($type), $validType)) $type = null;
		if ($type !== null) $order .= ' ' . $type;
		if (!empty($this->_orderBy)) {
			if (is_array($order)) {
				if (is_array($this->_orderBy)) $this->_orderBy = array_merge($this->_orderBy, $order);
				else $this->_orderBy .= ' , ' . implode(' , ', $order);
			} else {
				if (is_array($this->_orderBy)) $this->_orderBy = implode(' , ', $this->_orderBy) . ' , ' . $order;
				else $this->_orderBy .= ' , ' . $order;
			}
		} else $this->_orderBy = $order;

		return $this;
	}

	public function group(array|String $group) : self {
		if (is_array($group)) $this->_groupBy = array_merge($group);
		else $this->_groupBy[] = $group;
		return $this;
	}

	public function having(array|String $having) : self {
		if (is_array($having)) $this->_useHaving = array_merge($having);
		else $this->_useHaving[] = $having;
		return $this;
	}

	public function limit(array|int $limit, ?int $limit1 = null) : self {
		if (!empty($limit1) AND !is_array($limit)) $limit = array($limit, $limit1);
		if (is_array($limit)) $this->_useLimit = $limit;
		else {
			$_tmp = explode(',', $limit);
			$this->_useLimit[0] = trim($_tmp[0]);
			if (!empty($_tmp[1])) $this->_useLimit[1] = trim($_tmp[1]);
		}
		return $this;
	}

	public function reset() : self {
		parent::reset();
		$this->_dbStatement = null;
		$this->_join = '';
		$this->_groupBy = '';
		$this->_orderBy = '';
		$this->_useHaving = '';
		$this->_useLimit = null;
		$this->_rowCount = 0;
		return $this;
	}

	public function showQuery() : String {
		return $this->_dbStatement->queryString;
	}

	public function fetchAll(String $type = 'default') : array {
		if (empty($this->_dbStatement)) $this->execute();
		$ret = array();
		while($v = $this->_dbStatement->fetch()) {
			if ($type == 'array') $v = (array) $v;
			$ret[] = $v;
		}
		return $ret;
	}
}
