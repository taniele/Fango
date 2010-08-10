<?php
//Change the include path to point to your application directory
ini_set('include_path','../application:'. ini_get('include_path'));
ini_set('display_errors', 'on');

function __autoload($class) {

	if(strpos($class, "Controller") !== false) {

		include 'controllers/' . str_replace("Controller", "", $class) . '.php';
	}
}

//Change the include path of fango if you need
require_once '../fango/fango.php';
require_once 'plugins/com.studiomelonpie.quickon.php';

$fango = new Fango();
$quickon = QuickonPlugin::init()->getConfig();
$fango->run($quickon['rules']);