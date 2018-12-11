<?php

use s4y\admin\Admin;

class Tpl_Admin extends Tpl
{
    protected $_mod;
    protected $_action;

    function __construct($attr = []) {
        if (S4Y::access('admin')) {
            try {
                Admin::loadConfig();
                $this->_mod = isset($_REQUEST['mod']) ? $_REQUEST['mod'] : null;
                $modClass = '';
                if ($this->_mod === null) {
                    $this->_mod = 'admin';
                    $modClass = 's4y\\admin\\Admin';
                } else if (isset(Admin::$admin[$this->_mod])) {
                    $modClass = Admin::$admin[$this->_mod];
                } else {
                    throw new Exception('Модуль '.$this->_mod.' не определен');
                }

                $module = null;
                if (class_exists($modClass, true)) {
                    $module = new $modClass($this);
                } else throw new Exception('Класс ' . $modClass . ' не найден');

                if (isset($module)) {
                    $this->_action = (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'default');
                    $actionMethod = $this->_action . 'Action';
                    if (method_exists($module, $actionMethod)) {
                        $module->$actionMethod();
                        return;
                    } elseif ($this->_action != 'default') {
                        $this->_action = 'default';
                        $actionMethod = 'defaultAction';
                        if (method_exists($module, $actionMethod)) {
                            $module->$actionMethod();
                            return;
                        }
                    }
                }

                throw new Exception('Метод '.$this->_action.' не найден в модуле '.$this->_mod);
            } catch (Exception $e) {
                $this->setVar('ADMINPAGE_ERROR', $e->getMessage() . ' (' .
                    $e->getFile() . ':' . $e->getLine() . ')');
                $this->setVar('ADMINPAGE_CONTENT', '<pre>' . $e->getTraceAsString() . '</pre>');
                $this->setVar('ADMINPAGE_TITLE', 'Ошибка');
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