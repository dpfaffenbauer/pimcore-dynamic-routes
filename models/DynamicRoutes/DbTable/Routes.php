<?php

/**
 * @name				DynamicRoutes_DbTable_Routes
 * @author				Jaap Moolenaar
 */
class DynamicRoutes_DbTable_Routes extends Zend_Db_Table_Abstract {
	public function __construct($config = array()) {
		// initialise the DB
		// get the db and revision number
        $db	 = Pimcore_Resource_Mysql::get();
        $rev = Pimcore_Version::$revision;

		// older versions return the resource
        if($rev>1350)	Zend_Db_Table::setDefaultAdapter($db->getResource());
        else			Zend_Db_Table::setDefaultAdapter($db);

		// set the params
		$this->_name	= DynamicRoutes_Plugin::routesTableName();
		$this->_primary = 'id';

		parent::__construct($config);
	}

	public function countAll() {
		$sql = $this->select()
					->from($this, 'COUNT(*) AS cnt');

		return (int)$this->fetchRow($sql)->cnt;
	}
}