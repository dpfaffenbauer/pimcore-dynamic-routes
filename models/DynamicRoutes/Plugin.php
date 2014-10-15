<?php

/**
 * @name				DynamicRoutes_Plugin
 * @author				Jaap Moolenaar
 */
class DynamicRoutes_Plugin extends Pimcore_API_Plugin_Abstract implements Pimcore_API_Plugin_Interface {

    public static function needsReloadAfterInstall() {
        return true;
    }

    public static function install() {

		self::createTables();

        if (self::isInstalled()) {
            return "Plugin successfully installed.";
        } else {
            return "Plugin could not be installed";
        }
    }

    public static function uninstall() {

        self::dropTables();

        if (!self::isInstalled()) {
            return "Plugin successfully uninstalled.";
        } else {
            return "Plugin could not be uninstalled";
        }
    }

    public static function isInstalled() {
        return self::checkTables();
    }

	/**
	 * Returns the path to the translation file
	 * NOTE: Must return relative path below PIMCORE_PLUGINS_PATH
	 *
	 * @param string $language
	 * @return string
	 */
    public static function getTranslationFile($language) {
		// check wether the i18n file exists
        if(file_exists(PIMCORE_PLUGINS_PATH . "/DynamicRoutes/i18n/".$language.".csv")){
            return "/DynamicRoutes/i18n/".$language.".csv";
        }
        return "/DynamicRoutes/i18n/en.csv";

    }

	public static function routesTableName() {
		return 'plugin_dynamicroutes_routes';
	}

	public static function checkTables() {
        $result = true;

        try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

            $tables = $db->listTables();
            $needTables = array(self::routesTableName());

            $result = count(array_intersect($needTables, $tables)) == count($needTables);

            if(!$result) {
                Logger::info('Plugin not installed');
            }

        } catch (Zend_Db_Exception $e) {
			$result = false;
        }

        return $result;
	}

	public static function createTables() {
		$result = true;

		// do the tables exist already?
		if(self::checkTables()) return true;

        Logger::info('Creating tables');

		try {
			$db = Pimcore_API_Plugin_Abstract::getDb();

            $db->query("
			CREATE TABLE IF NOT EXISTS `".self::routesTableName()."` (
				`id`				INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`active`			CHAR(1) DEFAULT '1',
				`parent_id`			INT(11) UNSIGNED DEFAULT NULL ,
				`parent_module`		VARCHAR(255)  DEFAULT NULL,
				`parent_controller`	VARCHAR(255)  DEFAULT NULL,
				`parent_action`		VARCHAR(255)  DEFAULT NULL,
				`name`				VARCHAR(50)  DEFAULT NULL,
				`pattern`			VARCHAR(255) DEFAULT NULL,
				`reverse`			VARCHAR(255) DEFAULT NULL,
				`module`			VARCHAR(255) DEFAULT NULL,
				`controller`		VARCHAR(255) DEFAULT NULL,
				`action`			VARCHAR(255) DEFAULT NULL,
				`variables`			VARCHAR(255) DEFAULT NULL,
				`defaults`			VARCHAR(255) DEFAULT NULL,
				`priority`			INT(3) DEFAULT '0',
				PRIMARY KEY `id` (`id`),
						KEY `priority` (`priority`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
        } catch (Zend_Db_Exception $e) {
			$result = false;
			Logger::error("Failed to create tables; ".$e->getMessage());
        }

		return $result;
	}

	public static function dropTables() {
		$result = true;

		// don't the tables exist anymore?
		if(!self::checkTables()) return true;

        Logger::info('Dropping tables');

		try {
			$db  = Pimcore_API_Plugin_Abstract::getDb();

			$db->query("DROP TABLE IF EXISTS `".self::routesTableName()."`");
        } catch (Zend_Db_Exception $e) {
			$result = false;
			Logger::error("Failed to remove tables; ".$e->getMessage());
        }

		return $result;
	}

	private static $_routed = false;

	public function preDispatch() {
		if(self::$_routed) return; // check for 2x load
		self::$_routed = true;

		// get the front controller and router
		$front  = Zend_Controller_Front::getInstance();
		$router = $front->getRouter();

		// try to fetch out routes from cache
		$cacheKey = "plugin_dynamicroutes_routes";
//		if (true) {
		if (!$routes = Pimcore_Model_Cache::load($cacheKey)) {
            Logger::info('Routes were not cached: '.$cacheKey);

			// we'll cache an empty array at least
			$routes = array();

			// try to load the routes
			$oRoutesDB = new DynamicRoutes_Routes();
			try {
				$tmp_routes = $oRoutesDB->getRows("`active` = '1'");

				// convert the routes to DynamicRoutes_Route's
				foreach($tmp_routes as $route) {
					// add the route by document id
					if($route->parent_id && $document = Document_Page::getById($route->parent_id)) {
						$routes[$route->name] = new DynamicRoutes_Route($route, $document);
					}
					// add the route for all documents with this controller and action
					elseif($route->parent_controller && $route->parent_action) {
						// get the DB
						$db = Pimcore_API_Plugin_Abstract::getDb();

						// and all page id's with this controller/action pair
						if(!@$route->parent_module) {
							$data = $db->fetchAll("SELECT id FROM documents_page WHERE controller = ? AND action = ?", array($route->parent_controller, $route->parent_action));
						}
						// else get the pages for the module/controller/action combo
						else {
							$data = $db->fetchAll("SELECT id FROM documents_page WHERE module = ? AND controller = ? AND action = ?", array($route->parent_module, $route->parent_controller, $route->parent_action));
						}

						// create the routes.
						foreach($data as $row) {
							if($row['id'] && $document = Document_Page::getById($row['id'])){
								$routes[$route->name] = new DynamicRoutes_Route($route, $document);
							}
						}
					}
				}

				// write to cache
				Pimcore_Model_Cache::save($routes, $cacheKey, array("dynamicroutes","route"), null, 998);

			} catch( Exception $e) {
				Logger::error($e->getMessage());
			}
		}

		// walk through our routes
		foreach($routes as $routeName => $route) {
			$router->addRoute($routeName, $route);
		}

		$front->setRouter($router);
	}

	public function preDeleteObject(Object_Abstract $object){
		if($object instanceof Document) {
			$this->_table->delete("`parent_id` = '".$object->getId()."'");
		}
	}

}