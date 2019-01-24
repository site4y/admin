<?php

namespace s4y\admin;

use s4y\grid\Grid;
use s4y\form\Form;

/**
 * Базовый класс для стандартного раздела админки, выполняющего операции CRUD (Create, Read, Update, Delete)
 * Здесь уже реализованы стандартные action'ы для операций добавления, просмотра, редактирования и удаления
 * При наследовании от данного класса необходимо реализовать методы
 * - getGridOptions - возвращает массив параметров для грида (списка элементов)
 * - getFormOptions - возвращает массив параметров для формы (создание и редактирование элементов)
 * и определить класс модели в переменной modelClass
 *
 * Если необходима своя логика сохранения или удаления, можно перегрузить методы save и delete
 */
abstract class CRUD extends Base {
    const SUCCESS_ADD = 1;
    const SUCCESS_EDIT = 2;
    const SUCCESS_DELETE = 3;
    const ERROR_NOID = -1;
    const ERROR_NOITEM = -2;
    const ERROR_DELETE = -3;

    protected $_actions = [
        'default' => 'Список',
        'add' => 'Добавление',
        'edit' => 'Редактирование',
        'delete' => 'Удаление'
    ];

    protected $modelClass = 'Model_Base';

    static $baseUrl = '';

    /**
     * Метод инициализации. Здесь можно задать сообщения и заголовки страниц.
     * При переопределение в наследуемых классах, вызовите в первую очередь parent::init()
     */
    function init() {
        $this->_messages = [
            self::SUCCESS_ADD => 'Добавлено успешно',
            self::SUCCESS_EDIT => 'Изменения были сохранены',
            self::SUCCESS_DELETE => 'Удален успешно',
            self::ERROR_NOID => 'Не передан обязательный параметр id',
            self::ERROR_NOITEM => 'Элемент с указанным id не найден',
            self::ERROR_DELETE => 'Ошибка при удалении',
        ];
    }

    function defaultAction()
    {
        $grid = $this->getGrid();
        $this->gridPage($grid);
    }

    function gridPage($grid) {
        if ($this->isAjax()) {
            $grid->ajax();
        } else {
            if (empty($_REQUEST['returnUrl'])) {
                $this->setReturnUrl(parent::ADMIN_HOME, 'На главную');
            } else {
                $this->setReturnUrl($this->returnUrl, 'Назад');
            }

            $action = $this->action();
            if (isset($this->_actions[$action])) {
                $this->setTitle($this->_actions[$action]);
            }

            $this->setControls($grid->getAddBtn().' '.$grid->getExportBtn());
            $this->setContent($grid->render());
        }
    }

    function getGrid() {
        $options = $this->getGridOptions();
        if (!isset($options['url'])) $options['url'] = $this->url();
        if (!isset($options['ajax'])) $options['ajax'] = '/scripts'. $this->url();
        if (!isset($options['add'])) $options['add'] = $this->url(null, 'add').'&returnUrl={returnUrl}';
        if (!isset($options['edit'])) $options['edit'] = $this->url(null, 'edit'). '&id={id}&returnUrl={returnUrl}';
        if (!isset($options['delete'])) $options['delete'] = $this->url(null, 'delete').'&id={id}&returnUrl={returnUrl}';
        if (!isset($options['ajax-delete'])) $options['ajax-delete'] = '/scripts'.$this->url(null, 'delete').'&id={id}';
        return new Grid($options);
    }

    /**
     * Возвращает массив опций для списка элементов {@see \s4y\grid\Grid}. Опции url задавать необязательно,
     * если они шаблонные - см. метод {@see Admin_Mod_CRUD::getGrid()}
     * @return mixed Массив опций для грида {@see \s4y\grid\Grid}
     */
    abstract function getGridOptions();


    function getFormOptions($id) {
        return null;
    }

    function exportXlAction() {
        $page = $this->page;
        $full = !isset($page);
        $grid = $this->getGrid();
        $grid->export('excel', $this->_actions['default'], $this->module(), $full);
    }

    function addAction()
    {
        $this->setTitle($this->_actions['add']);
        $this->setReturnUrl($this->returnUrl, 'Отмена');
        $form = $this->getForm(null);

        if ($this->isPost()) {
            if ($form->validate()) {
                try {
                    $id = $this->save($form);
                    if ($id === true) {
                        $this->redirectBack(['success' => self::SUCCESS_ADD]);
                    } elseif ($id !== false) {
                        $this->redirect($this->url(null, 'edit', [
                            'id' => $id,
                            'returnUrl' => $this->returnUrl,
                            'success' => self::SUCCESS_ADD,
                        ]));
                    }
                }
                catch (Exception $e) {
                    $this->setError($e);
                }
            } else {
                $this->setError($form->renderErrors());
            }
        }

        $form->options['submit'] = 'Добавить';
        $this->setContent($form->render());
    }

    function editAction() {
        $this->setTitle($this->_actions['edit']);
        $this->setReturnUrl($this->returnUrl, 'Назад');
        $id = $this->id;
        if (!isset($id)) {
            $this->redirectBack(['error' => self::ERROR_NOID]);
        }
        
        $form = $this->getForm($id);
        
        if ($form === false) {
            $this->redirectBack(['error' => self::ERROR_NOITEM]);
        }

        if ($this->isPost()) {
            if ($form->validate()) {
                try {
                    \S4Y::getDb()->beginTransaction();
                    $res = $this->save($form);
                    \S4Y::getDb()->commit();
                    if ($res !== false) {
                        $this->redirectBack([
                            'success' => self::SUCCESS_EDIT
                        ]);
                    }
                }
                catch (Exception $e) {
                    \S4Y::getDb()->rollBack();
                    $this->setError($e);
                }
            } else {
                $this->setError($form->renderErrors());
            }
        }

        $form->options['submit'] = 'Сохранить';
        $this->setContent($form->render());
    }

    /**
     * Возвращает форму для добавления или редактирования элемента.
     * Должна возвращать false, если элемента с заданным ID не существует, либо форму
     * @param $id mixed ID элемента (редактирование) или NULL (если форма для добавления)
     * @return bool|Form
     */
    function getForm($id)
    {
        if ($id) {
            $className = $this->modelClass;
            $item = $className::getById($id);
            if (!$item) return false;
        }

        $formOptions = $this->getFormOptions($id);
        if (!$formOptions) throw new Exception('Form is not defined!');
        $form = new Form($formOptions);

        if ($id) {
            $form->populate($item);
        }

        return $form;
    }

    /**
     * Выполняет добавление элемента или сохранение изменений существующего элемента.
     * Верните ID добавленного элемента, если необходимо перейти
     * к странице редактирования, true - если нужно выполнить возврат
     * на предыдущую страницу, false - если не требуется предпринимать никаких действий
     * @param $form Form
     * @return bool|int
     */
    function save(&$form)
    {
        $id = $this->id;

        $values = $form->toArray();
        $className = $this->modelClass;

        if (!isset($id)) {
            $id = $className::insertRow($values);
        } else {
            $className::updateById($id, $values);
        }
        return true;
    }

    public function deleteAction() {
        $id = $this->id;
        if (empty($id)) {
            $this->error(self::ERROR_NOID);
        }

        try {
            if ($this->delete($id) === false) {
                $this->error(self::ERROR_NOITEM);
            }
            $this->success(self::SUCCESS_DELETE);
        }
        catch (Exception $e) {
            if ($this->isAjax()) {
                $this->ajaxError(self::ERROR_DELETE, $e->getMessage());
            } else {
                $this->setTitle($this->_messages[self::ERROR_DELETE]);
                $this->setError($e->getMessage());
                $this->setReturnUrl($this->returnUrl);
            }
        }
    }

    /**
     * Выполняет удаление элемента
     * Верните false в случае если такого элемента нет
     * @param $id
     * @return mixed
     */
    function delete($id)
    {
        $className = $this->modelClass;
        $item = $className::getById($id);
        if (!isset($item)) return false;
        $item->delete();
        return true;
    }


}