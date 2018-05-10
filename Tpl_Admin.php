<?php

class Tpl_Admin extends Tpl
{
    protected $_mod;
    protected $_action;

    function __construct($attr = array()) {
        if (S4Y::access('admin')) {

            $domainDir = S4Y::$domainsDir . DIRECTORY_SEPARATOR . S4Y::$domain;
            $moduleDir = $domainDir . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'Admin';
            $this->_mod = isset($_REQUEST['mod']) ? $_REQUEST['mod'] : 'default';
            $modFile = $moduleDir . DIRECTORY_SEPARATOR . ucfirst($this->_mod) . '.php';

            try {
                if (file_exists($modFile)) {
                    include_once($modFile);
                } elseif ($this->_mod != 'default') {
                    $this->_mod = 'default';
                    $modFile = $moduleDir . DIRECTORY_SEPARATOR . 'Default.php';
                    include_once($modFile);
                }
                $modClass = 'Admin_' . ucfirst($this->_mod);
                if (class_exists($modClass, false)) {
                    $module = new $modClass($this);
                } else throw new Exception('Класс ' . $modClass . ' не определен');
            } catch (Exception $e) {
                if ($this->_mod == 'default') {
                    $this->setVar('ADMINPAGE_ERROR', $e->getMessage() . ' (' .
                        $e->getFile() . ':' . $e->getLine() . ')');
                    $this->setVar('ADMINPAGE_CONTENT', '<pre>' . $e->getTraceAsString() . '</pre>');
                    $this->setVar('ADMINPAGE_TITLE', 'Ошибка');
                }
            }

            if (isset($module)) {
                $this->_action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'default');
                $actionMethod = $this->_action . 'Action';
                if (method_exists($module, $actionMethod)) {
                    $module->$actionMethod();
                } elseif ($this->_action != 'default') {
                    $this->_action = 'default';
                    $actionMethod = 'defaultAction';
                    if (method_exists($module, $actionMethod)) {
                        $module->$actionMethod();
                    }
                }
            }
        } 
    }

    function getModuleName() {
        return $this->_mod;
    }

    function getActionName() {
        return $this->_action;
    }
}