<?php

namespace s4y\admin;

abstract class Base {
    protected $_tpl;
    protected $_viewsDirs = null;
    protected $_vars;

    protected $_messages = [];

    const ADMIN_HOME = '/admin';

    public $returnUrl;

    function __construct($tpl)
    {
        $this->_tpl = $tpl;
        $domainDir = \S4Y::$domainsDir . DIRECTORY_SEPARATOR . \S4Y::$domain;

        $this->returnUrl = isset($_GET['mod']) ? '/admin?mod='.$_GET['mod'] : self::ADMIN_HOME;

        if (!empty($_REQUEST['returnUrl'])) {
            $this->returnUrl = urldecode($_REQUEST['returnUrl']);
        }

        $this->init();

        if ($code = $this->success) {
            if (isset($this->_messages[$code])) {
                $this->setSuccess($this->_messages[$code]);
            } else {
                $this->setSuccess('Операция выполнена успешно. Код: '.$code);
            }
        }
        if ($code = $this->error) {
            if (isset($this->_messages[$code])) {
                $this->setError($this->_messages[$code]);
            } else {
                $this->setError('Ошибка. Код: '.$code);
            }
        }
    }

    function init() {
        
    }

    function __set($name, $value)
    {
        $this->_vars[$name] = $value;
    }

    function __get($name)
    {
        if (isset($this->_vars[$name])) {
            return $this->_vars[$name];
        } elseif (isset($_REQUEST[$name])) {
            return $_REQUEST[$name];
        }
        return null;
        /*else if (isset($this->_tpl)) {
            return $this->_tpl->getVar($name);
        }*/
    }

    function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return true;
        }
        return false;
    }

    function getViewDirs() {
        if (!isset($this->_viewsDirs)) {
            $vd = [];

            $domains = \S4Y::$parentDomains->names;
            foreach ($domains as $d) {
                $vd[] = \S4Y::$domainsDir . DIRECTORY_SEPARATOR .$d.
                    DIRECTORY_SEPARATOR. 'views';
            }

            $className = get_called_class();
            $refl = new \ReflectionClass($className);
            if ($refl && $fileName = $refl->getFileName()) {
                $vd[] = dirname($fileName) . DIRECTORY_SEPARATOR . 'views';
            }

            $vd[] = dirname(__FILE__).DIRECTORY_SEPARATOR.'views';

            $this->_viewsDirs = $vd;
        }
        return $this->_viewsDirs;
    }

    protected $_viewDir = null;
    protected $_viewName = null;

    function view($name = null)
    {
        if (!empty($this->_vars)) extract($this->_vars);
        $viewsDirs = $this->getViewDirs();
        $mod = $this->module();

        $seekToDir = false;
        if (!isset($name)) {
            $seekToDir = $this->_viewDir;
            $name = $this->_viewName;
        }

        foreach ($viewsDirs as $viewsDir) {
            if ($seekToDir) {
                if ($viewsDir != $seekToDir) {
                    continue;
                } else {
                    $seekToDir = false;
                    continue;
                }
            }

            if (!empty($mod)) {
                $viewFile = $viewsDir . DIRECTORY_SEPARATOR . $mod . DIRECTORY_SEPARATOR . $name . '.phtml';

                if (file_exists($viewFile)) {
                    $prevViewDir = $this->_viewDir;
                    $prevViewName = $this->_viewName;
                    $this->_viewDir = $viewsDir;
                    $this->_viewName = $name;
                    ob_start();
                    include $viewFile;
                    return ob_get_clean();
                    $this->_viewDir = $prevViewDir;
                    $this->_viewName = $prevViewName;
                }
            }


            $viewFile = $viewsDir . DIRECTORY_SEPARATOR . 
                DIRECTORY_SEPARATOR. $name . '.phtml';

            if (file_exists($viewFile)) {
                $prevViewDir = $this->_viewDir;
                $prevViewName = $this->_viewName;
                $this->_viewDir = $viewsDir;
                $this->_viewName = $name;
                ob_start();
                include $viewFile;
                return ob_get_clean();
                $this->_viewDir = $prevViewDir;
                $this->_viewName = $prevViewName;
            }
        }
        return false;
    }

    function setContent($content)
    {
        $this->_tpl->setVar('ADMINPAGE_CONTENT', $content);
    }

    function setTitle($title)
    {
        $this->_tpl->setVar('ADMINPAGE_TITLE', $title);
    }

    function setDescription($desc)
    {
        $this->_tpl->setVar('ADMINPAGE_DESC', $desc);
    }

    function setControls($controls)
    {
        $this->_tpl->setVar('ADMINPAGE_CONTROLS', $controls);
    }

    function setError($error)
    {
        if (is_a($error, 'Exception')) {
            $this->_tpl->setVar('ADMINPAGE_ERROR', $error->getMessage());
        } else {
            $this->_tpl->setVar('ADMINPAGE_ERROR', $error);
        }
    }

    function setSuccess($msg)
    {
        $this->_tpl->setVar('ADMINPAGE_SUCCESS', $msg);
    }

    function setWarning($msg)
    {
        $this->_tpl->setVar('ADMINPAGE_WARNING', $msg);
    }

    function setReturnUrl($returnUrl, $text) {
        $this->returnUrl = $returnUrl;
        if ($returnUrl == self::ADMIN_HOME) {
            $this->_tpl->setVar('ADMINPAGE_RETURN_ICON', '<i class="glyphicon glyphicon-home" style="color:#888; font-size:12px; line-height: 16px"></i>&nbsp;');
        }
        $this->_tpl->setVar('ADMINPAGE_RETURN_URL', htmlspecialchars($returnUrl));
        if ($text) $this->_tpl->setVar('ADMINPAGE_RETURN_TEXT', $text);
    }

    function action() {
        if (isset($this->_tpl)) {
            return $this->_tpl->getActionName();
        }
        return '';
    }

    function module() {
        if (isset($this->_tpl)) {
            return $this->_tpl->getModuleName();
        }
        return '';
    }

    function url($mod = null, $action = null, $params = [])
    {
        if (!isset($action)) $action = $this->action();
        if (!isset($mod)) $mod = $this->module();
        $modAction = [];
        if (isset($mod) && $mod != 'default') $modAction['mod'] = $mod;
        if (isset($action) && $action != 'default') $modAction['action'] = $action;
        $query = http_build_query(array_merge($modAction, $params));
        return '/admin' . (($query === '') ? '' : '?' . $query);
    }

    function currentUrl() {
        $url = parse_url($_SERVER['REQUEST_URI']);
        if ($url) {
            parse_str($url['query'], $query);
            if (isset($query['success'])) unset($query['success']);
            if (isset($query['error'])) unset($query['error']);
            $urlParts['path'] = $url['path'];
            $urlParts['query'] = http_build_query($query);
            $urlParts['fragment'] = '';
            return $this->unparse_url($urlParts);
        }
        return $_SERVER['REQUEST_URI'];
    }

    protected function unparse_url($parsed_url) {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = (isset($parsed_url['fragment']) && $parsed_url['fragment'] !== '') ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    function redirect($url) {
        header('Location: '.$url);
        exit;
    }

    function redirectBack($params = []) {
        if (empty($this->returnUrl)) $this->returnUrl = '/admin';
        $url = $this->returnUrl;
        if (!empty($params)) {
            $url .= ((strpos($this->returnUrl, '?') !== FALSE) ? '&' : '?') . http_build_query($params);
        }
        $this->redirect($url);
    }

    function ajaxError($code = null, $message = '') {
        $errStr = '';
        if (isset($code) && isset($this->_messages[$code])) {
            $errStr = $this->_messages[$code];
        }
        if (!empty($message)) {
            if ($errStr != '') $errStr .= ': '.$message;
        }
        if ($errStr == '') {
            $errStr = 'Неизвестная ошибка. Код: '.$code;
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => $errStr]);
        exit;
    }

    function ajaxSuccess($code = null, $message = '') {
        $msg = '';
        if (isset($code) && isset($this->_messages[$code])) {
            $msg = $this->_messages[$code];
        }
        if (!empty($message)) {
            if ($msg != '') $msg .= ': '.$message;
            else $msg = $message;
        }
        if ($msg == '') {
            $msg = 'Операция успешно завершена. Код: '.$code;
        }
        echo json_encode([
            'reload' => true,
            'message' => $msg
        ]);
        exit;
    }

    function error($code = null, $message = '') {
        if ($this->isAjax()) {
            $this->ajaxError($code, $message);
        } else {
            $this->redirectBack(['error' => $code]);
        }
    }

    function success($code, $message = '') {
        if ($this->isAjax()) {
            $this->ajaxSuccess($code, $message);
        } else {
            $this->redirectBack(['success' => $code]);
        }
    }
}