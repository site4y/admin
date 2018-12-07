<?php

namespace s4y\admin;

use s4y\grid\Grid;

class Admin extends Base
{
    static $admin = null;
    static $menu = null;
    static $tasks = null;

    const ERROR_NOTASKID = -1;
    const ERROR_INVALIDTASKID = -2;

    static function loadConfig() {
        // TODO cache config
        if (self::$menu !== null) return;
        $domains = \S4Y::$parentDomains->names;
        foreach ($domains as $d) {
            $adminConfig = \S4Y::$domainsDir . DIRECTORY_SEPARATOR . $d.
                DIRECTORY_SEPARATOR.'admin.php';
            if (file_exists($adminConfig)) {
                self::$menu = require $adminConfig;
                break;
            }
        }
        if (self::$menu === null) self::$menu = [];
        self::_parseConfig(self::$menu);
    }

    private static function _parseConfig(&$menu) {
        foreach ($menu as $i => $m) {
            if (isset($m['$class'])) {
                self::$admin[$i] = $m['$class'];
            }
            if (isset($m['$taskClass'])) {
                self::$tasks[$i] = $m['$taskClass'];
            }
            if (is_array($m)) self::_parseConfig($m);
        }
    }

    function defaultAction()
    {
        $this->setTitle('Добро пожаловать в админ-панель '.\S4Y::$domain.'!');
        $this->setContent($this->view('default'));
    }

    function infoAction()
    {
        $this->setReturnUrl(self::ADMIN_HOME, 'На главную');
        $this->setTitle('Информация');
        $this->setContent($this->view('info'));
    }

    function phpinfoAction()
    {
        phpinfo();
        die;
    }

    function __construct($tpl)
    {
        parent::__construct($tpl);

        if ($this->error) {
            switch ($this->error) {
                case self::ERROR_NOTASKID:
                    $this->setError('Не указан идентификатор задачи');
                    break;
                case self::ERROR_INVALIDTASKID:
                    $this->setError('Неверный идентификатор задачи');
                    break;
            }
        }
    }

    function taskAction()
    {
        if (isset($_GET['back']) && $_GET['back'] == 'home') {
            $this->setReturnUrl(parent::ADMIN_HOME, 'На главную');
        } else {
            $this->setReturnUrl($this->returnUrl, 'К списку задач');
        }

        $taskId = $this->task;
        if (!isset($taskId)) $this->redirectBack(['error' => self::ERROR_NOTASKID]);
        if (!isset(self::$tasks[$taskId])) $this->redirectBack(['error' => self::ERROR_INVALIDTASKID]);

        $taskClass = self::$tasks[$taskId];
        $this->setTitle($taskClass::$name);
        $this->setDescription($taskClass::$desc);

        if ($this->isPost()) {
            $task = $taskClass::exec();

            if ($task->error) {
                $this->setError($task->error);
            }

            if ($task->success) {
                $this->setSuccess('Задача успешно выполнена');
            }

            $this->log = $task->log;
        }

        $this->last = $taskClass::getLastExecuteDate();
        if (!$this->last) $this->last = 'никогда';

        $this->setContent($this->view('task'));
    }

    function taskListAction()
    {
        $this->setReturnUrl(parent::ADMIN_HOME, 'На главную');

        $this->setTitle('Запуск административных задач');

        $content = '<dl>';
        foreach (self::$tasks as $id => $taskClass) {
            $last = $taskClass::getLastExecuteDate();
            if ($last) {
                $last = strtotime($last);
                $last = date('d.m.Y H:i:s', $last);
            } else {
                $last = 'никогда';
            }
            $content .= '<dt><a href="' . self::$_baseUrl . '&action=exec&task=' . $id . '">' . $taskClass::$name . '</a></dt>
                <dd>' . $taskClass::$desc . '<br><i>Последнее выполнение: ' . $last . '</i></dd><hr>';
        }
        $content .= '</dl>';

        $this->setContent($content);
    }

}