<?php

/**
 * @name				DynamicRoutes_RoutesController
 * @author				Jaap Moolenaar
 */
class DynamicRoutes_RoutesController extends Pimcore_Controller_Action_Admin {

	/**
	 * Handles all CRUD calls from ext
	 */
	public function proxyAction(){
		$oRoutesDB = new DynamicRoutes_Routes();

        if ($this->_getParam("data")) {

            if ($this->getUser()->isAllowed("routes")) {

				// default return
				$json = array("success" => false, "data" => array());
				switch($this->_getParam("xaction")) {
					case 'destroy':
						$id = Zend_Json::decode($this->_getParam("data"));

						if($oRoutesDB->delete($id)) {
							$json['success'] = true;
						}
						break;

					case 'update':
						$data = Zend_Json::decode($this->_getParam("data"));

						if($data = $oRoutesDB->update($data, $data["id"])) {
							$json['success'] = true;
							$json['data']		= $data;
						}
						break;

					case 'create':
						$data = Zend_Json::decode($this->_getParam("data"));

						if($data = $oRoutesDB->create($data)) {
							$json['success'] = true;
							$json['data']		= $data;
						}
						break;
				}

				// clear our cache
				Pimcore_Model_Cache::clearTag("dynamicroutes");

				// return the data
				$this->_helper->json($json);

            } else {
                Logger::err("user [" . $this->getUser()->getId() . "] attempted to modify dynamic routes, but has no permission to do so.");
            }
        }
        else {
            // get routes
			$count  = $this->_getParam("limit");
			$offset = $this->_getParam("start");

			// check to see if an order has been set
			$order = null;
			if($this->_getParam("sort")) {
				$order = $this->_getParam("sort").' '.$this->_getParam("dir");
			}

			// check to see if a filter has been set
			$where = null;
            if($this->_getParam("filter")) {
				$filter = "'%" . mysql_real_escape_string($this->_getParam("filter")) . "%'";
                $where = "	`name`			LIKE " . $filter . "
						OR	`pattern`		LIKE " . $filter . "
						OR	`reverse`		LIKE " . $filter . "
						OR	`controller`	LIKE " . $filter . "
						OR	`action`		LIKE " . $filter;
            }

			// check for thet total nr of rows
			$total = count( $oRoutesDB->getRows($where) );

            $routes = array();
            foreach ($oRoutesDB->getRows($where, $order, $count, $offset) as $route) {
                $routes[] = $route->toArray();
            }

            $this->_helper->json(array("data" => $routes, "success" => true, "total" => $total));
        }
	}

	public function documentlistAction() {
		$documents = Document::getList();
		$json = array('documents' => array(
			array('id' => 0, 'value' => '0')
		));

		foreach($documents as $document) { /* @var $document Document */
			if($document->getId() == 1) continue;
			$json['documents'][] = array(
				'id'	=> $document->getId(),
				'value'	=> $document->getId().': '.$document->getFullPath()
			);
		}

		$this->_helper->json($json);
	}
}