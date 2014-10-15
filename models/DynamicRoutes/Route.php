<?php

/**
 * @name                DynamicRoutes_Route
 * @author              Jaap Moolenaar
 *
 * BESCHRIJVING *
 * ---------------------------------------------------------------------------
 *
 * ---------------------------------------------------------------------------
 *
 * HISTORY *
 * ---------------------------------------------------------------------------
 *
 * ---------------------------------------------------------------------------
 */
class DynamicRoutes_Route extends Zend_Controller_Router_Route_Regex {

    /**
     * The row from our table
     * @var Zend_Db_Table_Row
     */
    private $_row;

    /**
     * The PimCore document
     * @var Document
     */
    private $_document;

    public function __construct($row, $document) {
        $this->_row      = $row;
        $this->_document = $document;

        // get the pattern and strip the delimiter, which should always be set
        $pattern    = $row->pattern;
        $delimiter  = substr($pattern, 0, 1);                   // something like '/' or '#'
        $pattern    = preg_replace("#(^".preg_quote($delimiter)."|".preg_quote($delimiter)."$)#", "", $pattern);    // also replace the starting slash
        $pattern    = preg_replace("#^(\/|\\\/)#", "", $pattern);   // also replace the starting slash
        $reverse    = preg_replace("#^/#", "", $row->reverse);  // also replace the starting slash in the reverse

        $docPath = $document->getFullPath();

        // no starting slash
        $docPath = substr($docPath, 0, 1) == '/' ? substr($docPath, 1) : $docPath;

        // but we do need a trailing slash
        $docPath = $this->_trSlash($docPath);

        // add the document url, which is what this plugin is actually for
        $pattern = preg_quote($docPath,'#').preg_replace('/\\?#/' ,'\#', $pattern);
        $reverse = $docPath.$reverse;

        $map = explode(',', ','.$row->variables); unset($map[0]); // fake a value

        // TODO: this won't do, how does this work in PimCore?
        // $defaults = explode(',', $row->defaults);
        $defaults = array();

        Logger::debug("Made route for: '$pattern'");

        parent::__construct($pattern, $defaults, $map, $reverse);
    }

    /**
     * If we get a match, try and determine the module/controller/action
     *
     * @see parent::match()
     * @param string $path
     * @param boolean $partial
     * @return array|false
     */
    public function match($path, $partial = false) {
        $params =  parent::match($path, $partial);

        if($params !== false) {

            // determine the mod.cnt.act
            $module     = $this->_module    ($params);
            $controller = $this->_controller($params);
            $action     = $this->_action    ($params);

            if($module)     $params['module']       = $module;
            if($controller) $params['controller']   = $controller;
            if($action)     $params['action']       = $action;

            // pass along the document as per pimcore design
            $params['document'] = $this->_document;
        }

        return $params;
    }


    /**
     * Assembles a URL path defined by this route
     *
     * @param  array $data An array of name (or index) and value pairs used as parameters
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = array(), $reset = false, $encode = false, $partial = false)
    {
        if ($this->_reverse === null) {
            require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception('Cannot assemble. Reversed route is not specified.');
        }

        $defaultValuesMapped  = $this->_getMappedValues($this->_defaults, true, false);
        $matchedValuesMapped  = $this->_getMappedValues($this->_values, true, false);
        $dataValuesMapped     = $this->_getMappedValues($data, true, false);

        // handle resets, if so requested (By null value) to do so
        if (($resetKeys = array_search(null, $dataValuesMapped, true)) !== false) {
            foreach ((array) $resetKeys as $resetKey) {
                if (isset($matchedValuesMapped[$resetKey])) {
                    unset($matchedValuesMapped[$resetKey]);
                    unset($dataValuesMapped[$resetKey]);
                }
            }
        }

        // merge all the data together, first defaults, then values matched, then supplied
        $mergedData = $defaultValuesMapped;
        $mergedData = $this->_arrayMergeNumericKeys($mergedData, $matchedValuesMapped);
        $mergedData = $this->_arrayMergeNumericKeys($mergedData, $dataValuesMapped);

        if ($encode) {
            foreach ($mergedData as $key => &$value) {
                $value = urlencode($value);
            }
        }

        // this is the bit that's different
        // try to find a '%s' which means use sprintf
        if(strstr($this->_reverse, '%s')) {
            $return = @vsprintf($this->_reverse, $mergedData);
        }
        // else try to find if there's a % at all
        // tehn replace %<variable_name> with its matching value
        elseif(strstr($this->_reverse, '%')) {
            $map = explode(',', ','.$this->_row->variables); unset($map[0]); // fake a value (again)
            $return = $this->_reverse;

            // walk through the map, and just str_replace the values
            foreach($map as $key => $val) {
                if(!$val) continue;

                $return = str_replace('%'.$val, $mergedData[$key], $return);
            }
        }
        
        $parametersGet = array();
        
        foreach($data as $key => $value)
        {
            if(!array_key_exists($key, $dataValuesMapped))
            {
                $parametersGet[$key] = $value;
            }
        }

        // optional get parameters
        if(!empty($parametersGet)) {
            if($encode) {
                $getParams = array_urlencode($parametersGet);
            } else {
                $getParams = array_toquerystring($parametersGet);
            }
            $return .= "?" . $getParams;
        }

        if ($return === false) {
            require_once 'Zend/Controller/Router/Exception.php';
            throw new Zend_Controller_Router_Exception('Cannot assemble. Too few arguments?');
        }

        return $return;

    }

    /**
     * Get the module from the params
     * @param array $params
     * @return string
     */
    private function _module($params) {
        return $this->_specParam('module', $params);
    }

    /**
     * Get the controller from the params
     * @param array $params
     * @return string
     */
    private function _controller($params) {
        return $this->_specParam('controller', $params);
    }

    /**
     * Get the action from the params
     * @param array $params
     * @return string
     */
    private function _action($params) {
        return $this->_specParam('action', $params);
    }


    /**
     * Get a variable from the row or document
     * And do some replacing if its a named param %name
     *
     * @param string $key
     * @param array $params
     * @return string
     */
    private function _specParam($key, $params) {
        $value = $this->_rowOrDoc($key);

        return $this->_selfOrNamedParam($value, $params);
    }

    /**
     * Get a value from _row or _document, _row is preferred
     *
     * @param string $key
     * @return string
     */
    private function _rowOrDoc($key) {
        return $this->_row->{$key} ? $this->_row->{$key} : $this->_document->{$key};
    }

    /**
     * Return de same value as passed
     * Or replace a piece of the value using named pairs
     *
     * @param string $key
     * @param array $params
     * @return string
     */
    private function _selfOrNamedParam($key, $params) {
        if(preg_match('#%(.*)#', $key, $matches)) {
            if(array_key_exists($matches[1], $params)) {
                $key = str_replace($matches[0], $params[$matches[1]], $key);
            }
        }

        return $key;
    }

    /**
     * Add a tailikng slash if needed
     * @param string $sUrl
     * @return string
     */
    private function _trSlash($sUrl) {
        return substr($sUrl, -1) == '/' ? $sUrl : $sUrl.'/';
    }

}