<?php
/*********************************************************************
    class.kanban.php

    Shared Kanban board logic for the Tasks module. Used by both the
    staff UI (scp/ajax.php) and the public REST API (api/kanban.php).

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once INCLUDE_DIR.'class.task.php';
require_once INCLUDE_DIR.'class.forms.php';

class Kanban {

    // Board columns. The data model stores an open/closed flag; the open
    // state is sub-divided into Open / Doing / Verifying lanes which are
    // persisted via the spare KANBAN_DOING / KANBAN_VERIFYING flag bits.
    static $STATUSES = array(
        'open'      => 'Open',
        'doing'     => 'Doing',
        'verifying' => 'Verifying',
        'closed'    => 'Completed',
    );

    // Completed tasks older than this are hidden from the board.
    const COMPLETED_DAYS = 7;

    static function getStatuses() {
        $statuses = array();
        foreach (self::$STATUSES as $id => $label) {
            $statuses[] = array(
                'id' => $id,
                'label' => __($label),
            );
        }
        return $statuses;
    }

    static function statusToFlag($status) {
        return strcasecmp($status, 'closed') ? 'open' : 'closed';
    }

    static function laneOf(Task $T) {
        if ($T->isClosed())
            return 'closed';
        if (($T->flags & TaskModel::KANBAN_VERIFYING))
            return 'verifying';
        if (($T->flags & TaskModel::KANBAN_DOING))
            return 'doing';
        return 'open';
    }

    static function setLane(Task $T, $lane) {
        $T->flags = ($T->flags & ~TaskModel::KANBAN_DOING & ~TaskModel::KANBAN_VERIFYING);
        if ($lane == 'doing')
            $T->flags = ($T->flags | TaskModel::KANBAN_DOING);
        elseif ($lane == 'verifying')
            $T->flags = ($T->flags | TaskModel::KANBAN_VERIFYING);
    }

    static function getBoard($filters=array(), $staff=null) {
        TaskForm::ensureDynamicDataView();

        $query = Task::objects();

        // Apply quick filters derived from the request.
        if (isset($filters['status']) && $filters['status']) {
            $flag = self::statusToFlag($filters['status']);
            $sq = new Q(array('flags__hasbit' => TaskModel::ISOPEN));
            if ($flag == 'closed')
                $sq->negate();
            $query->filter($sq);
        }

        if (isset($filters['dept_id']) && $filters['dept_id']) {
            $query->filter(array('dept_id' => $filters['dept_id']));
        }

        if (isset($filters['team_id']) && $filters['team_id']) {
            $query->filter(array('team_id' => $filters['team_id']));
        }

        if (isset($filters['assignee']) && $filters['assignee']) {
            $assignee = $filters['assignee'];
            if (is_string($assignee) && $assignee[0] == 's') {
                $query->filter(array('staff_id' => (int) substr($assignee, 1)));
            } elseif (is_string($assignee) && $assignee[0] == 't') {
                $query->filter(array('team_id' => (int) substr($assignee, 1)));
            } else {
                $query->filter(array('staff_id' => (int) $assignee));
            }
        }

        if (isset($filters['due']) && $filters['due']) {
            switch ($filters['due']) {
            case 'overdue':
                $query->filter(array('isoverdue' => 1));
                break;
            case 'today':
                $query->filter(array('duedate__gte' => date('Y-m-d 00:00:00'),
                                    'duedate__lte' => date('Y-m-d 23:59:59')));
                break;
            default:
                if (!empty($filters['due_start']))
                    $query->filter(array('duedate__gte' => $filters['due_start']));
                if (!empty($filters['due_end']))
                    $query->filter(array('duedate__lte' => $filters['due_end']));
            }
        }

        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->filter(Q::any(array(
                'number__startswith' => $q,
                'cdata__title__contains' => $q,
            )));
        }

        // Impose staff visibility constraints when a staff member is provided.
        if ($staff && $staff instanceof Staff) {
            $visibility = Q::any(
                new Q(array('flags__hasbit' => TaskModel::ISOPEN,
                            'staff_id' => $staff->getId()))
            );
            $visibility->add(new Q(array(
                'ticket__staff_id' => $staff->getId(),
                'ticket__status__state' => 'open')));
            if (!$staff->showAssignedOnly() && ($depts = $staff->getDepts()))
                $visibility->add(new Q(array('dept_id__in' => $depts)));
            if (($teams = $staff->getTeams()) && count(array_filter($teams)))
                $visibility->add(new Q(array(
                    'team_id__in' => array_filter($teams),
                    'flags__hasbit' => TaskModel::ISOPEN)));
            $query->filter(new Q($visibility));
        }

        $query->order_by('-updated');

        $board = array();
        foreach (self::$STATUSES as $id => $label) {
            $board[$id] = array(
                'id' => $id,
                'label' => __($label),
                'cards' => array(),
            );
        }

        foreach ($query as $T) {
            $col = self::laneOf($T);
            if ($col == 'closed') {
                $closed = $T->closed;
                if ($closed && strtotime((string) $closed)
                        < strtotime('-'.self::COMPLETED_DAYS.' days'))
                    continue;
            }
            $board[$col]['cards'][] = self::toCard($T);
        }

        return array(
            'columns' => self::getStatuses(),
            'board' => $board,
            'count' => array_sum(array_map(function($c){ return count($c['cards']); }, $board)),
        );
    }

    static function toCard(Task $T) {
        $assignee = null;
        if ($T->getStaffId()) {
            $staff = $T->getStaff();
            if ($staff)
                $assignee = array(
                    'type' => 'staff',
                    'id' => $T->getStaffId(),
                    'name' => (string) new AgentsName(
                        $staff->getFirstName().' '.$staff->getLastName()),
                );
        } elseif ($T->getTeamId()) {
            $team = $T->getTeam();
            if ($team)
                $assignee = array(
                    'type' => 'team',
                    'id' => $T->getTeamId(),
                    'name' => $team->getName(),
                );
        }

        $dept = $T->getDept();
        $duedate = $T->getDueDate();

        return array(
            'id' => $T->getId(),
            'number' => $T->getNumber(),
            'title' => (string) $T->getTitle(),
            'status' => self::laneOf($T),
            'assignee' => $assignee,
            'dept' => $dept ? (string) $dept->getName() : '',
            'dept_id' => $T->getDeptId(),
            'due' => $duedate ? Format::datetime($duedate) : '',
            'isoverdue' => $T->isOverdue(),
            'url' => 'tasks.php?id='.$T->getId(),
        );
    }

    static function moveCard($id, $status, $assignee=null, $staff=null, $comments='') {
        if (!($task = Task::lookup($id)))
            return array('error' => __('Unknown or invalid task ID.'));

        if ($staff && !$task->checkStaffPerm($staff, Task::PERM_EDIT))
            return array('error' => __('Permission denied.'));

        $flag = self::statusToFlag($status);
        if (($flag == 'closed' && $task->isOpen())
                || ($flag == 'open' && $task->isClosed())) {
            if (!$task->setStatus($flag, $comments))
                return array('error' => __('Unable to change task status.'));
        }

        if ($flag == 'open') { self::setLane($task, $status); $task->save(); }

        if ($assignee) {
            $errors = array();
            $form = AssignmentForm::instantiate(array('assignee' => $assignee));
            if (!$task->assign($form, $errors))
                return array('error' => $errors['err']
                    ?: __('Unable to reassign task.'));
        }

        return self::toCard($task);
    }

    static function createTask($vars, $staff) {
        global $thisstaff;

        if (!$staff || !$staff->hasPerm(Task::PERM_CREATE, false))
            return array('error' => __('Permission denied.'));

        $old = $thisstaff;
        $thisstaff = $staff;

        $iform = array();
        if (!empty($vars['dept_id']))
            $iform['dept_id'] = $vars['dept_id'];
        if (!empty($vars['duedate']))
            $iform['duedate'] = $vars['duedate'];
        if (!empty($vars['assignee']))
            $iform['assignee'] = $vars['assignee'];

        $default = array();
        if (!empty($vars['title']))
            $default['title'] = $vars['title'];
        if (!empty($vars['description']))
            $default['description'] = $vars['description'];

        $data = array(
            'object_id' => 0,
            'object_type' => 'A',
            'default_formdata' => $default,
            'internal_formdata' => $iform,
            'staffId' => $staff->getId(),
            'poster' => $staff,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?: '127.0.0.1',
        );

        $errors = array();
        $task = Task::create($data);

        $thisstaff = $old;

        if (!$task)
            return array('error' => __('Unable to create task.'));

        return self::toCard($task);
    }

    static function readTask($id, $staff=null) {
        if (!($task = Task::lookup($id)))
            return array('error' => __('Unknown or invalid task ID.'));

        if ($staff && !$task->checkStaffPerm($staff))
            return array('error' => __('Permission denied.'));

        return self::toCard($task);
    }

    static function updateTask($id, $vars, $staff=null) {
        if (!($task = Task::lookup($id)))
            return array('error' => __('Unknown or invalid task ID.'));

        if ($staff && !$task->checkStaffPerm($staff, Task::PERM_EDIT))
            return array('error' => __('Permission denied.'));

        $errors = array();

        if (isset($vars['status']) && $vars['status']) {
            $flag = self::statusToFlag($vars['status']);
            if (($flag == 'closed' && $task->isOpen())
                    || ($flag == 'open' && $task->isClosed())) {
                if (!$task->setStatus($flag))
                    return array('error' => __('Unable to change task status.'));
            }
            if ($flag == 'open')
                self::setLane($task, $vars['status']);
        }

        if (!empty($vars['assignee'])) {
            $form = AssignmentForm::instantiate(array('assignee' => $vars['assignee']));
            if (!$task->assign($form, $errors))
                return array('error' => $errors['err']
                    ?: __('Unable to reassign task.'));
        }

        if (!empty($vars['dept_id'])) {
            $task->dept_id = $vars['dept_id'];
        }

        if (!empty($vars['duedate'])) {
            $task->duedate = date('Y-m-d G:i', Misc::dbtime($vars['duedate']));
        }

        if (!empty($vars['title'])) {
            $tf = TaskForm::getInstance($task->getId(), true);
            $tf->setAnswer('title', $vars['title']);
            $tf->save();
        }

        if (!$task->save())
            return array('error' => __('Unable to update task.'));

        return self::toCard($task);
    }
}
