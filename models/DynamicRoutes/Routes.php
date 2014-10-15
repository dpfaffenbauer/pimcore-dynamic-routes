<?php

/**
 * @name				DynamicRoutes_Routes
 * @author				Jaap Moolenaar
 */
class DynamicRoutes_Routes {

	/**
	 * The table
	 * @var Zend_Db_Table_Abstract
	 */
    private $_table;

    public function __construct() {
		// set the table class
        $this->_table = new DynamicRoutes_DbTable_Routes();
    }

	/**
	 * @see DynamicRoutes_DbTable_Routes::countAll()
	 * @return integer
	 */
	public function countRows () {
		return (int)$this->_table->countAll();
	}

	/**
	 * Get rows from the DB
	 *
	 * @see Zend_Db_Table_Abstract::fetchAll()
	 * @return array
	 */
	public function getRows($where = null, $order = 'priority', $count = null, $offset = null) {
		return $this->_table->fetchAll($where, $order, $count, $offset);
	}

	/**
	 * Crud
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function create($data) {
		$iID = $this->_table->insert($data);

		if($iID) {
			$data['id'] = $iID;
			return $data;
		}

		return false;
	}

	/**
	 * crUd
	 *
	 * @param array $data
	 * @param integer $id
	 * @return boolean
	 */
	public function update($data, $id) {
		if((int)$id > 0) {
			return $this->_table->update($data, "`id` = '".$id."'") ? $data : false;
		}

		return false;
	}


	/**
	 * cruD
	 *
	 * @param integer $id
	 * @return boolean
	 */
	public function delete($id){
		if((int)$id > 0) {
			return $this->_table->delete("`id` = '".$id."'");
		}
	}

	/**
	 * Generate the Staticroute's
	 * @deprecated StaticRoutes cant be injected! But this is how to generate them...
	 *
	 * @param array $routes append routes to this array
	 * @return Staticroute[]
	 */
	public function getStaticRoutes($routes = array()) {
		$rows = $this->getRows();

		foreach($rows as $row) {

			// generate a new static route
			$oRoute		= new Staticroute();
			$oDocument	= Document::getById($row->parent_id);

			// check whether the document has loaded
			if($oDocument) {
				$oRoute->setName		($row->name);

				// get the pattrern and stip the dlimniter, which should always be set
				$pattern	= $row->pattern;
				$delimiter	= substr($pattern, 0, 1);					// something like '/' or '#'
				$pattern	= substr($pattern, 1);						//
				$pattern	= preg_replace("#^(\/|\\\/)#", "", $pattern);	// also replace the starting slash
				$reverse	= preg_replace("#^/#", "", $row->reverse);	// also replace the starting slash inb the reverse

				$oRoute->setPattern		($delimiter.preg_quote($this->trSlash($oDocument->getFullPath()),$delimiter).$pattern);
				$oRoute->setReverse		($this->trSlash($oDocument->getFullPath()).$reverse);

				// leave mod/cnt/act empty to use paren document's
				if(!$row->module && !$row->controller && !$row->action) {
					$oRoute->setModule		($oDocument->module);
					$oRoute->setController	($oDocument->controller);
					$oRoute->setAction		($oDocument->action);
				} else {
					$oRoute->setModule		($row->module);
					$oRoute->setController	($row->controller);
					$oRoute->setAction		($row->action);
				}

				$oRoute->setVariables	($row->variables);
				$oRoute->setDefaults	($row->defaults);
				$oRoute->setPriority	($row->priority);

				// log this route
				Logger::debug("Made route for '{$oRoute->getPattern()}', '{$oRoute->getReverse()}'");

				$routes[] = $oRoute;
			}
		}

		return $routes;
	}

	// add trailing slash
	// doubled up...
	private function trSlash($sUrl) {
		return rtrim($sUrl, '/').'/';
	}
}