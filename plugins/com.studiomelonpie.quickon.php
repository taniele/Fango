<?
/**
 * Quickon - Configuration Plugin for Fango
 *
 * @author Daniel Bernardi, Studio Melonpie <dan@studiomelonpie.com>
 **/
class QuickonPlugin {

	/**
	 * This array specifies which mandatory options must be present for each section. If you need to check for specific sections,
	 * add them to the main array. The default array checks if 'driver', 'name', 'host', 'user' and 'pass' are all present in
	 * the section "database" of your configuration file.
	 *
	 * @var array
	 **/
	protected static $configProperties = array('database' => array('driver', 'name', 'host', 'user', 'pass'));
	protected static $config;
	protected static $environment = null;
	protected static $instance;
	
	const CONF_PATH = '../conf/';
	
	private function __construct($configName = null) {
		if (($configName === null || !file_exists(QuickonPlugin::CONF_PATH . $configName)) && file_exists(QuickonPlugin::CONF_PATH . $_SERVER["HTTP_HOST"])) {

			$configName = $_SERVER["HTTP_HOST"];
		}
		elseif(file_exists(QuickonPlugin::CONF_PATH . "default")) {
			$configName = "default";
		}
		else {
			trigger_error("Quickon: No configuration file found.");
		}

		$this->config = $this->getConfigFile(QuickonPlugin::CONF_PATH . $configName);

		if (array_key_exists("database", $this->config)) {
			$this->parseConfig("database");
			FangoDB::connect($this->config["database"]["driver"] . ":dbname=" . $this->config["database"]["name"] . ";host=" . $this->config["database"]["host"], $this->config["database"]["user"], $this->config["database"]["pass"]);
		}

		if (file_exists(QuickonPlugin::CONF_PATH . 'rules')) {

			$this->config["rules"] = $this->getConfigFile(QuickonPlugin::CONF_PATH . "rules");
		}
		elseif (!array_key_exists("rules", $this->config)) {
			$this->config["rules"] = array();
		}
		
	}
	
	public static function init($configName = null) {
		
		if(!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c($configName);
		}		

		return self::$instance;
	}
	
	public function getConfig() {
		return $this->config;
	}
	
	private function getConfigFile($filename) {
		return json_decode(file_get_contents($filename), true);
	}
	
		
	/**
	 * Parses the the specified section to check if all the required parameters are filled in
	 *
	 * @param string $section The configuration section to check.
	 * @return void
	 **/
	private function parseConfig($section) {

		if(!array_key_exists($section, self::$configProperties)) return;

		$missingProperties = array_diff(self::$configProperties[$section], array_keys(self::$config[$section]));

		if(!empty($missingProperties)) {
			trigger_error("The following properties are missing for section '{$section}': '" . implode("', '", $missingProperties) . "'", E_USER_ERROR);
			return;
		}
		
		if (isset(self::$config["environment"])) {

			self::$environment = self::$config;
		}
	}
	
	public function isDevelopment() { return self::$environment == "development"; }
	public function isStaging() { return self::$environment == "staging"; }
	public function isLive() { return self::$environment == "live"; }
	
}
