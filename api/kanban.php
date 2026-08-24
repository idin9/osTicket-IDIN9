<?php
/*********************************************************************
    kanban.php

    Kanban REST API controller for the Tasks module.

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once INCLUDE_DIR.'class.api.php';
require_once INCLUDE_DIR.'class.kanban.php';

class KanbanApiController extends ApiController {

    function board($format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        $this->staffFromKey($key);

        $filters = array();
        foreach (array('status', 'dept_id', 'team_id', 'assignee', 'due',
                'due_start', 'due_end', 'q') as $f) {
            if (isset($_GET[$f]) && $_GET[$f] !== '')
                $filters[$f] = $_GET[$f];
        }

        $data = Kanban::getBoard($filters, $this->_staff);
        return $this->jsonResponse($data);
    }

    function statuses($format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        return $this->jsonResponse(array('statuses' => Kanban::getStatuses()));
    }

    function move($format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        $this->staffFromKey($key);

        $data = $this->getRequest($format, false);
        if (!isset($data['id']) || !isset($data['status']))
            return $this->exerr(400, __('Task id and status required'));

        $result = Kanban::moveCard($data['id'], $data['status'],
            isset($data['assignee']) ? $data['assignee'] : null,
            $this->_staff,
            isset($data['comments']) ? $data['comments'] : '');

        if (isset($result['error']))
            return $this->exerr(400, $result['error']);

        return $this->jsonResponse($result);
    }

    function createTask($format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        $this->staffFromKey($key);

        $data = $this->getRequest($format, false);
        $result = Kanban::createTask($data, $this->_staff);

        if (isset($result['error']))
            return $this->exerr(400, $result['error']);

        return $this->jsonResponse($result, 201);
    }

    function readTask($id, $format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        $this->staffFromKey($key);

        $result = Kanban::readTask($id, $this->_staff);
        if (isset($result['error']))
            return $this->exerr(404, $result['error']);

        return $this->jsonResponse($result);
    }

    function updateTask($id, $format) {
        if (!($key = $this->requireApiKey()))
            return $this->exerr(401, __('API key not authorized'));

        $this->staffFromKey($key);

        $data = $this->getRequest($format, false);
        $result = Kanban::updateTask($id, $data, $this->_staff);

        if (isset($result['error']))
            return $this->exerr(400, $result['error']);

        return $this->jsonResponse($result);
    }

    protected function staffFromKey($key) {
        global $thisstaff;
        $this->_staff = $key->getStaff();
        if ($this->_staff)
            $thisstaff = $this->_staff;
    }

    protected function jsonResponse($data, $code=200) {
        Http::response($code, JsonDataEncoder::encode($data), 'application/json');
    }
}
