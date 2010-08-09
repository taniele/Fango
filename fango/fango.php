<?php
/**
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author Andrea Giardina <andrea.giardina@crealabs.it>
* @link http://github.com/agiardina/Fango
*/
class Fango {
	
	/**
	 * @var string the default controller if no controller specified
	 */
	public $default_controller = 'default';

	/**
	 * @var the default action if action not specified
	 */
	public $default_action = 'index';

	/**
	 * @var the default action if action not found
	 */
	public $notfound_action = 'error404';

	/**
	 * @var the controller after the routing
	 */
	public $controller;

	/**
	 * @var the action after the routing
	 */
	public $action;

	/**
	 * @var array the params
	 */
	public $params = array();
	
	/**
	 * @var FangoDB
	 */
	public $db;

	/**
	 * @var FangoEvent 
	 */
	static $onLoad;

	/**
	 * @var FangoEvent 
	 */
	public $beforeDispatch;

	/**
	 * @var FangoEvent
	 */
	public $afterDispatch;


	/**
	 * @param FangoDB $db
	 */
	function  __construct($db = null) {
		$this->db = $db;

		$this->beforeDispatch = new FangoEvent('beforeDispatch');
		$this->afterDispatch = new FangoEvent('afterDispatch');
		
		self::$onLoad->fire($this);
	}

	/**
	 * Extend this method to run a code every time a controller is created
	 */
	function init() {}

	/**
	 * @param array $custom_rules
	 * @param  $subject
	 * @return Fango
	 */
	function route($custom_rules = array(),$subject = ''){

		//If subject has not been declared we guess that from $_SERVER
		if (!$subject) {
			$script = $_SERVER['PHP_SELF'];
			$script = preg_replace("~\w*\.php$~",'',$script);

			if (isset($_SERVER['REQUEST_URI'])) {
				$subject = $_SERVER['REQUEST_URI'];
				$subject = preg_replace('~\?.*$~','',$subject);

				if (strpos($subject,$script)===0) {
					$subject = substr($subject,strlen($script));
				}
			}
		}

		//Rules merged
		$rules[] = '(\w+)/(\w+)/(.*)$ controller=$1,action=$2,params=$3';
		$rules[] = '(\w+)/(\w+)/?$ controller=$1,action=$2';
		$rules[] = '(\w+)/?$ controller=$1';
		if ($custom_rules) {
			if (!is_array($custom_rules)) $custom_rules = array($custom_rules);
			$rules = array_merge($custom_rules,$rules);
		}
		
		$controller = $this->default_controller;
		$action = $this->default_action;
		$params = array();
		
		foreach ($rules as $rule) {
			list($rule,$replacement) = @preg_split("/[\t\s]/",$rule,-1,PREG_SPLIT_NO_EMPTY); //Split the rule from the replace

			if (preg_match("~$rule~",$subject)) {

				$replacement = preg_replace("~$rule~",$replacement,$subject);
				$a_conf = explode(',',$replacement);

				foreach ($a_conf as $conf) {
					list($var,$value) = explode('=',$conf);
					if ($var == 'controller' && $value) {
						$controller = $value;
					} elseif ($var == 'action' && $action) {
						$action = $value;
					} elseif ($var == 'params') {
						$_params = explode('/',$value);
						foreach ($_params as $i=>$param) {
							if ($i % 2 == 0) {
								$param_name = $param;
							} else {
								$params[$param_name] = $param;
							}
						}
					} else {
						$params[$var] = $value;
					}
				}
				break;
			}
		}
		$this->controller = $controller;
		$this->action = $action;
		$this->params = $params;

		return $this;
	}
	
	/**
	 * @param string $controller
	 * @param string $action
	 * @param array $params 
	 */
	function dispatch($controller=null,$action=null,array $params=null){
		if ($controller)$this->controller = $controller;
		if ($action) $this->action = $action;
		if ($params!==null) $this->params = $params;

		$e = $this->beforeDispatch->fire($this); //BeforeDispatch Event Fired
		if ($e->preventDefault()) return;

		$class_name = "{$this->controller}Controller";
		$method_name = "{$this->action}Action";

		//If the method doesn't exist use the default controller and method
		if (!class_exists($class_name) || !method_exists($class_name, $method_name)) {
			$this->controller = $this->default_controller;
			$this->action = $this->notfound_action;
			$class_name = "{$this->controller}Controller";
			$method_name = "{$this->action}Action";
		}
		
		$obj_controller = new $class_name($this);
		$obj_controller->init();
		$obj_controller->$method_name();
		
		$this->afterDispatch->fire($this); //AfterDispatch Event Fired
	}

	/**
	 * Shortcut for fango->route()->dispatch();
	 * @param array $custom_rules
	 * @param string $subject
	 */
	function run($custom_rules = array(),$subject = ''){
		return $this->route($custom_rules,$subject)->dispatch();
	}

	/**
	 * @param string $name of the request param
	 * @param mixed $default value 
	 */
	static function request($name,$default_value=null) {
		if (!isset($_REQUEST[$name])) {
			$_REQUEST[$name] = $default_value;
		}
		return $_REQUEST[$name];
	}

}

class FangoController {
	/**
	 * @var Fango The fango front controller
	 */
	public $fango;

	/**
	 * @var FangoEvent 
	 */
	static $onLoad;


	/**
	 * @param Fango $fango 
	 */
	function __construct(Fango $fango) {
		$this->fango = $fango;
		self::$onLoad->fire($this);
	}
	
	/**
	 * setter/getter for params
	 * 
	 * @param string $param
	 * @param mixed $value
	 * @return mixed
	 */
	function param($param,$value=null){
		if ($value!==null) {
			$this->fango->params[$param] = $value;
		}
		if (isset($this->fango->params[$param])) {
			return $this->fango->params[$param];
		}

		return null;
	}

	/**
	 *
	 * @param string $name of the table
	 * @param string $pk name 
	 * @return FangoModel
	 */
	function model($name,$pk = null) {
		if (isset($this->fango->db)) {
			return $this->fango->db->model($table,$pk);
		}
	}
	
	function error404Action() {
		header("HTTP/1.0 404 Not Found");
		echo "<h1>Page Not Found</h1>";
	}

}

class FangoView {
	/**
	 * The name of the view, used by render as imput/select etc
	 * @var string
	 */
	protected $_name;

	/**
	 * The value for the view, used by render as input/select etc
	 * @var string
	 */
	protected $_value;

	/**
	 * The options array, used by render as select
	 * @var array
	 */
	protected $_options = array();

	/**
	 * The template to render
	 * @var string
	 */
	protected $_template;

	/**
	 * @var FangoEvent 
	 */
	static $onLoad;

	
	/**
	 * @param string $name of the view
	 * @param string $template to render
	 */
	function __construct($name = null,$template=null){
		if ($name) $this->_name = $name;
		if (!$template && $name) {
			$this->_template = "templates/$name.phtml";
		}
		self::$onLoad->fire($this);
	}

	/**
	 * Return the string to include jquery
	 * @param string $version of jquery
	 * @return string
	 */
	function includeJQuery($version = "1.4.1") {
		return "<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/{$version}/jquery.min.js\" type=\"text/javascript\"></script>";
	}

	/**
	 * Render the view as imput
	 * @param string $properties the html properties
	 * @return string the html input
	 */
	function input($properties=''){
		$value = htmlspecialchars($this->_value);
		return "<input name=\"$this->_name\" value=\"$value\" $properties />";
	}
	
	/**
	 * Render the view as select
	 * @param string $properties the html properties
	 * @return string the html select
	 */
	function select($properties=''){

		if ($this->_options == array_values($this->_options)) { //This is a standard array and not a map
			$this->_options = array_combine($this->_options,$this->_options); // Tranform a standard array in map value=>value
		} 
		$sreturn = "<select name=\"$this->_name\" $properties >";
		foreach ($this->_options as $key=>$label) {
			$key = htmlspecialchars($key);
			if ($this->_value == $key) {
				$selected = 'selected="selected"';
			} else {
				$selected = '';
			}
			$sreturn .= "<option $selected value=\"$key\">$label</option>";
		}
		$sreturn .= "</select>";
		return $sreturn;
	}
	
	/**
	 * Render the view as textarea
	 * @param string $properties the html properties
	 * @return string
	 */
	function textarea($properties=''){
		$value = htmlspecialchars($this->_value);
		return "<textarea name=\"$this->_name\" $properties >$value</textarea>";
	}

	/**
	 * Render the default view or the view passed as param
	 * @param string $template
	 * @return string
	 */
	function render($template=null){
		ob_start();
		if ($template) $this->_template = $template;
		include $this->_template;
		return ob_get_clean();
	}

	/**
	 * @param string $value
	 * @return string 
	 */
	function value($value=null) {
		return $this->prop('value',$value);
	}

	/**
	 * @param string $template
	 * @return string
	 */
	function template($template=null) {
		return $this->prop('template',$template);
	}

	/**
	 * @param array $options
	 * @return array
	 */
	function options($options=array()) {
		return $this->prop('options',$options);
	}

	/**
	 * @param string $name
	 * @return string 
	 */
	function name($name=null) {
		return $this->prop('name',$name);
	}

	protected function prop($name,$value=null) {
		$prop = "_$name";
		if ($value!==null) {
			$this->$prop = $value;
		}
		return $this->$prop;
	}

	/**
	 * Call the render method
	 * @return string
	 */
	function __toString() {
		return $this->render();
	}

	/**
	 * Yeld over all subview
	 * @return FangoView
	 */
	function yeld() {
		static $i = -1;
		$vars = array_keys(get_object_vars($this));

		$n = count($vars);
		while (++$i < $n) {
			$var = $vars[$i];
			$obj = $this->$var;
			if ($obj instanceof FangoView) {
				return $obj;
			}
		}
		$i = -1;
		return false;
	}

	/**
	 * Return all values of subviews
	 * @return array
	 */
	function getValues() {
		$ret = array();
		while ($view = $this->yeld()) {
			$name = $view->name();
			if ($name) $ret[$name] = $view->value();
		}
		return $ret;
	}
	
}

class FangoDB extends PDO {

	/**
	 * @var FangoEvent 
	 */
	static $onLoad;

	/**
	 * @see PDO::__construct
	 */
	function __construct($dsn, $username=null, $password=null,$driver_options=array()) {
		parent::__construct($dsn,$username,$password,$driver_options);
		self::$onLoad->fire();
	}
	
	/**
	 * Instance a table model an inject it with the database
	 * @param string $table name
	 * @param string $pk name
	 * @return FangoModel
	 */
	function model($table,$pk = null) {
		return new FangoModel($table,$pk,$this);
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	function execute($sql,$params = null){
		$sth = $this->prepare($sql);
		$sth->execute($params);
		return $sth;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return array 
	 */
	function getAll($sql,$params = null){
		$sth = $this->execute($sql,$params);
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return array 
	 */
	function getRow($sql,$params = null){
		$sth = $this->execute($sql,$params);
		return $sth->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	function getCol($sql,$params = null){
		$sth = $this->execute($sql,$params);
		return $sth->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return mixed
	 */
	function getOne($sql,$params = null) {
		$sth = $this->execute($sql,$params);
		$res = $sth->fetch(PDO::FETCH_NUM);
		if (is_array($res)) return array_shift($res);
	}
}

class FangoModel {
	/**
	 *
	 * @var FangoDB
	 */
	public $db;

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var string
	 */
	public $pk;

	/**
	 * @var array
	 */
	protected $fields = array();

	/**
	 * @var array
	 */
	protected $where = array();

	/**
	 * @var array
	 */
	protected $order = array();

	/**
	 * @var array
	 */
	protected $limit = array();

	/**
	 * @var FangoEvent 
	 */
	static $onLoad;

	/**
	 * @var array
	 */
	public $events = array('beforeUpdate','beforeInsert','beforeDelete','afterUpdate','afterInsert','afterDelete');

	/**
	 * @param string $name
	 * @param string $pk
	 * @param FangoDB $db
	 */
	function __construct($name,$pk=null,$db=null) {
		$this->name = $name;
		$this->pk = $pk;
		$this->db = $db;
		
		self::$onLoad->fire($this);
	}

	/**
	 * Set the fields to extract
	 * @param array $fields
	 * @return FangoModel 
	 */
	function fields($fields) {
		if (!is_array($fields)) $fields = func_get_args();

		$this->fields = $fields;
		return $this;
	}

	/**
	 * Add a where clause in the PDO format 
	 * @param string $clause
	 * @param array $params
	 * @return FangoModel
	 */
	function where($clause,$params=null) {
		if ($params !== null && !is_array($params)) {
			$params = array($params);
		}
		$this->where['clause'][] = $clause;
		if (isset($this->where['params'])) {
			$this->where['params'] = array_merge($this->where['params'],$params);
		} else {
			$this->where['params'] = $params;
		}
		
		return $this;
	}

	/**
	 * Add an order clause
	 * @param string $order field
	 * @param string $direction (asc,desc)
	 * @return FangoModel
	 */
	function order($order,$direction=null) {
		$this->order[] = array($order,$direction);
		return $this;
	}

	/**
	 * Set limit and offset
	 * @param int $limit
	 * @param int $offset
	 * @return FangoModel 
	 */
	function limit($limit,$offset = null) {
		$this->limit = array($limit,$offset);
		return $this;
	}

	/**
	 * @return array params to use with the where
	 */
	function params() {
		if (isset($this->where['params'])) {
			return $this->where['params'];
		}
		return array();
	}

	/**
	 * @return string the built select using fields, where, order and limit params
	 */
	function asSelect() {
		$fields = '*';
		$where = '';
		$order = '';
		$limit = '';

		if ($this->fields) $fields = join(',',$this->fields);

		if ($this->where) $where = 'WHERE ' . join (' AND ',$this->where['clause']);
		
		if ($this->order) {
			$order = "ORDER BY ";
			foreach ($this->order as $ao) {
				$order .= trim("{$ao[0]} {$ao[1]}") . ',';
			}
			$order = substr($order,0,-1);
		}
		
		if ($this->limit) {
			$limit = "LIMIT " . $this->limit[0];
			if ($this->limit[1]) {
				$limit .= " OFFSET " . $this->limit[1];
			}
		}

		$select = trim("SELECT $fields FROM {$this->name} $where $order $limit");
		return $select;
	}

	/**
	 * @return array all rows
	 */
	function getAll() {
		return $this->db->getAll($this, $this->params());
	}

	/**
	 * @return array the first row
	 */
	function getRow() {
		return $this->db->getRow($this, $this->params());
	}

	/**
	 * @return array the first col
	 */
	function getCol() {
		return $this->db->getCol($this, $this->params());
	}

	/**
	 * @return mixed first value of the first row
	 */
	function getOne() {
		return $this->db->getOne($this, $this->params());
	}

	/**
	 * @return int the number of row of the built select
	 */
	function count() {
		$sql = "SELECT count(*) FROM (".$this->asSelect().") AS A";
		return $this->db->getOne($sql,$this->params());
	}

	/**
	 * Insert a row in the related table
	 * @param array $row
	 * @return PDOStatement
	 */
	function insert($row) {
		$e = $this->beforeInsert->fire($this,$row); //BeforeInsert event fired
		list (,$row) = $e->params;
		if ($e->preventDefault()) return;
		
		$keys = array_keys($row);

		$fields = join(',',$keys);
		$values = ':' . join(',:',$keys);
		$sql = "INSERT INTO {$this->name} ($fields) VALUES($values)";
		$stm = $this->db->execute($sql, $row);

		$this->afterInsert->fire($this,$row); //AfterInsert event fired
		return !(bool)$stm->errorCode();
	}

	/**
	 * Shortcut for PDO::lastInsertID
	 * @param string $seqname
	 * @return int
	 */
	 function lastInsertID($seqname = null) {
		 return $this->db->lastInsertId($seqname);
	 }

	/**
	 * Delete the row specified by the pk param in the related table
	 * @param mixed $pk
	 * @return DBOStatement
	 */
	function delete($pk) {
		$e = $this->beforeDelete->fire($this,$pk);
		list (,$pk) = $e->params;
		if ($e->preventDefault()) return;
		
		$this->requirePK();
		list($where,$params) = $this->pkParts(null,$pk);
		$sql = "DELETE FROM {$this->name} WHERE $where";
		$stm = $this->db->execute($sql,$params);

		$this->afterDelete->fire($this,$pk);
		return !(bool)$stm->errorCode();
	}

	/**
	 * Update a row
	 * @param array $row
	 * @param mixed $pk
	 * @return PDOStatement
	 */
	function update($row,$pk=null) {
		$e = $this->beforeUpdate->fire($this,$row,$pk); //BeforeUpdate event fired
		list (,$row,$pk) = $e->params;
		if ($e->preventDefault()) return;

		$this->requirePK();
		list($where,$pk_params) = $this->pkParts($row,$pk);
		$sql = "UPDATE {$this->name} SET ";
		foreach ($row as $field=>$value) {
			$sql .= "{$field}=:{$field},";
		}
		$sql = substr($sql,0,-1) . " WHERE $where";
		$params = array_merge($pk_params,$row);

		$return = $this->db->execute($sql,$params);
		$this->afterUpdate->fire($this,$row,$pk,$return); //AfterUpdate event fired
		return $return;
	}
	
	/**
	 * The arg row is new?
	 * @param array $row to consider
	 * @param mixed $pk
	 * @return <type> 
	 */
	function isNew($row,$pk=null) {
		list($pk_where,$pk_values) = $this->pkParts($row,$pk);
		$statement = "SELECT 1 FROM {$this->name} WHERE $pk_where";
		return !$this->db->getOne($statement,$pk_values);
	}

	/**
	 * Reset fields, where, limit and order, useful to reuse the model to run a different query
	 * @param string $what
	 * @return FangoModel
	 */
	function reset($what = null) {
		if (in_array($what,array('fields','where','limit','order'))){
			$this->$what = array();
		} else {
			$this->fields = array();
			$this->where = array();
			$this->limit = array();
			$this->order = array();
		}
		return $this;
	}

	/**
	 * Return an array with pk where and params ready to be used to compose a query 
	 * @param string $row
	 * @param mixed $pk_value
	 * @return array
	 */
	function pkParts($row,$pk_value = null) {
		$this->requirePK();

		$pk = $this->pk;
		if (!is_array($pk)) $pk = array($pk);

		if (!$pk_value) { //If no pk specified we read the pk from the row
			$pk_value = array_intersect_key($row,array_flip($pk));
		} elseif (!is_array($pk_value)) { //We need pk_value as key=>value
			$pk_value = array($pk[0]=>$pk_value);
		}

		if (array_keys($pk_value) != $pk) throw new Exception('PK not valid');

		$pk_params = array();
		$pk_where = '';
		foreach ($pk as $p) {
			$pk_params["__PK__{$p}"] = $pk_value[$p]; //We had __PK__ to key to allow updating on pk (set id:id where :id=__PK__id)
			$pk_where .= "{$p} = :__PK__{$p} AND ";
		}
		$pk_where = substr($pk_where,0,-5);
		return array($pk_where,$pk_params );
	}

	/**
	 * throw an exception if pk is not defined
	 */
	protected function requirePK() {
		if (!isset($this->db) | !isset($this->pk)) throw new Exception("DB or PK not defined");
	}

	/**
	 * return the model as select
	 * @return string
	 */
	function  __toString() {
		return $this->asSelect();
	}

	/**
	 * Used to lazy loading events
	 */
	function __get($name) {
		FangoEvent::lazyLoading($this,$name);
		return $this->$name;
	}
}

class FangoEvent {
	/**
	 * @var array the params passed to the obsvers
	 */
	public $params;

	/**
	 * @var array list of observer for the events
	 */
	protected $observers = array();

	/**
	 * @var boolean
	 */
	protected $prevent = false;
	/**
	 * @var string event name
	 */
	protected $name;

	/**
	 * @param string $name the event name
	 */
	function __construct($name) {
		$this->name = $name;
	}

	/**
	 * Getter for protected properties, the name can be read but not changed
	 * @param string $name
	 */
	function __get($name) {
		if (isset($this->$name)) return $this->$name;
	}

	/**
	 * @param boolean $prevent
	 * @return boolean
	 */
	function preventDefault($prevent = null) {
		if ($prevent !== null) $this->prevent = $prevent;
		return $this->prevent;
	}

	/**
	 * @param FangoObserver $observer 
	 */
	function addObserver($observer) {
		if (!in_array($observer,$this->observers)) $this->observers[] = $observer;
	}
	
	/**
	 * @param FangoObserver $observer
	 */
	function deleteObserver($observer) {
		$key = array_search($observer,$this->observers);
		if ($key !== false) unset($this->observers[$key]);
	}

	/**
	 * Fire the event
	 */
	function fire() {
		$this->params = func_get_args();
		foreach ($this->observers as $observer) {
			$method = $this->name;
			if (method_exists($observer,$method)) {
				$observer->$method($this);
			}
		}
		return $this;
	}

	/**
	 * @param Object $subject
	 */
	static function lazyLoading($subject,$event) {
		if (isset($subject->events) && is_array($subject->events)) {
			$subject->$event = new FangoEvent($event);
			return $subject->$event;
		}
		throw new Exception("Property $event doesn't exists");
	}
}

class FangoObserver {

	function subscribe($event) {
		$event->addObserver($this);
		return $this;
	}

	function unsubscribe($event) {
		$event->deleteObserver($this);
		return $this;
	}
}

class FangoPlugin  extends FangoObserver {

	static $loaded = array();
	
	function  __construct() {
		if (isset($this->pluginto)) $this->pluginto($this->pluginto);
	}

	function pluginto($class) {
		$pro = get_class_vars($class);
		if (isset($pro['onLoad']) && $pro['onLoad'] instanceof FangoEvent) {
			$this->subscribe($pro['onLoad']);
		}
	}

	function onLoad($e) {
		if (is_callable(array($this, 'plug'))) $this->plug($e->params[0]);
	}

	static function load($name) {
		if (!isset(self::$loaded[$name])) {
			$class = "{$name}Plugin";
			self::$loaded[$name] = new $class;
		}
		return self::$loaded[$name];
	}

}

//onLoad events set to class level
Fango::$onLoad = new FangoEvent('onLoad');
FangoDB::$onLoad = new FangoEvent('onLoad');
FangoModel::$onLoad = new FangoEvent('onLoad');
FangoView::$onLoad = new FangoEvent('onLoad');
FangoController::$onLoad = new FangoEvent('onLoad');