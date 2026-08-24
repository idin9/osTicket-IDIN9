<?php
/*********************************************************************
    kanban.inc.php

    Kanban board for the Tasks module. Server-rendered columns hydrated
    with data over AJAX; drag-and-drop powered by jQuery UI Sortable.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2014 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if(!defined('OSTSTAFFINC') || !$thisstaff) die('Unauthorized');

require_once INCLUDE_DIR.'class.kanban.php';

$columns = Kanban::getStatuses();
$depts = Dept::getActiveDepartments();
$teams = $thisstaff->getTeams();
?>
<div id="kanban-filters" class="clearfix" style="margin-bottom:12px;">
  <div class="pull-left flush-left">
    <input type="text" id="kanban-search" class="input-medium"
        placeholder="<?php echo __('Search'); ?>" style="width:200px;">
    <select id="kanban-assignee" class="input-medium">
      <option value=""><?php echo __('All Assignees'); ?></option>
      <option value="s<?php echo $thisstaff->getId(); ?>"><?php
        echo Format::htmlchars($thisstaff->getName()); ?></option>
      <?php foreach ($teams as $tid) {
        if ($team = Team::lookup($tid)) { ?>
        <option value="t<?php echo $tid; ?>"><?php
            echo Format::htmlchars($team->getName()); ?></option>
      <?php } } ?>
    </select>
    <select id="kanban-dept" class="input-medium">
      <option value=""><?php echo __('All Departments'); ?></option>
      <?php foreach ($depts as $id => $name) { ?>
        <option value="<?php echo $id; ?>"><?php echo Format::htmlchars($name); ?></option>
      <?php } ?>
    </select>
    <select id="kanban-team" class="input-medium">
      <option value=""><?php echo __('All Teams'); ?></option>
      <?php foreach ($teams as $tid) {
        if ($team = Team::lookup($tid)) { ?>
        <option value="<?php echo $tid; ?>"><?php
            echo Format::htmlchars($team->getName()); ?></option>
      <?php } } ?>
    </select>
    <select id="kanban-due" class="input-medium">
      <option value=""><?php echo __('Any Due Date'); ?></option>
      <option value="overdue"><?php echo __('Overdue'); ?></option>
      <option value="today"><?php echo __('Due Today'); ?></option>
    </select>
  </div>
  <div class="pull-right flush-right">
    <span id="kanban-count" class="faded"></span>
  </div>
</div>

<div id="kanban-board" class="clearfix">
  <?php foreach ($columns as $c) { ?>
  <div class="kanban-column" data-status="<?php echo $c['id']; ?>" style="
      float:left; width:31%; margin-right:1.5%; min-height:300px;
      background:#f4f6f8; border:1px solid #dde1e6; border-radius:4px;
      padding:6px;">
    <h3 class="kanban-column-header" style="margin:4px 4px 8px; font-size:14px;">
      <?php echo Format::htmlchars($c['label']); ?>
      <span class="kanban-column-count faded">(0)</span>
    </h3>
    <div class="kanban-cards" style="min-height:60px;"></div>
  </div>
  <?php } ?>
</div>

<div id="kanban-error" class="error-banner" style="display:none;"></div>

<script type="text/javascript">
$(function() {
  if (typeof $.ui === 'undefined') {
    $('<script>').attr('src', 'js/jquery-ui-1.13.2.custom.min.js').appendTo('head');
  }

  function cardHtml(card) {
    var overdue = card.isoverdue ? ' class="Icon overdueTicket"' : '';
    var assignee = card.assignee
      ? card.assignee.name
      : '<?php echo __('Unassigned'); ?>';
    return ''
      + '<div class="kanban-card" data-id="' + card.id + '" style="'
      + 'background:#fff; border:1px solid #dde1e6; border-radius:3px;'
      + 'padding:6px 8px; margin-bottom:6px; cursor:move;">'
      + '<a href="' + card.url + '" class="preview"' + overdue + '>'
      + '<strong>' + $('<div>').text(card.number).html() + '</strong> '
      + $('<div>').text(card.title).html() + '</a>'
      + '<div class="faded" style="font-size:11px; margin-top:4px;">'
      + $('<div>').text(assignee).html()
      + (card.dept ? ' &middot; ' + $('<div>').text(card.dept).html() : '')
      + (card.due ? '<br>' + $('<div>').text(card.due).html() : '')
      + '</div></div>';
  }

  function loadBoard() {
    $('#kanban-error').hide();
    var params = {
      assignee: $('#kanban-assignee').val(),
      dept_id: $('#kanban-dept').val(),
      team_id: $('#kanban-team').val(),
      due: $('#kanban-due').val(),
      q: $('#kanban-search').val()
    };
    $.ajax({
      url: 'ajax.php/tasks/kanban',
      data: params,
      dataType: 'json'
    }).done(function (data) {
      $('.kanban-cards').empty();
      var total = 0;
      $.each(data.board, function (status, col) {
        var $col = $('.kanban-column[data-status="' + status + '"]');
        if (!$col.length) return;
        $.each(col.cards, function (i, card) {
          $col.find('.kanban-cards').append(cardHtml(card));
          total++;
        });
        $col.find('.kanban-column-count').text('(' + col.cards.length + ')');
      });
      $('#kanban-count').text(total + ' <?php echo __('tasks'); ?>');
      enableSortable();
    }).fail(function (xhr) {
      $('#kanban-error').text(xhr.responseText || '<?php
        echo __('Unable to load board'); ?>').show();
    });
  }

  function enableSortable() {
    $('.kanban-cards').sortable({
      connectWith: '.kanban-cards',
      items: '.kanban-card',
      placeholder: 'kanban-placeholder',
      receive: function (event, ui) {
        var $card = ui.item;
        var $column = $(this).closest('.kanban-column');
        var status = $column.data('status');
        var id = $card.data('id');
        $.ajax({
          url: 'ajax.php/tasks/kanban/move',
          type: 'POST',
          data: { id: id, status: status },
          dataType: 'json'
        }).done(function () {
          loadBoard();
        }).fail(function (xhr) {
          $('#kanban-error').text(xhr.responseText || '<?php
            echo __('Unable to move task'); ?>').show();
          loadBoard();
        });
      }
    });
  }

  $('#kanban-search').on('keyup', debounce(loadBoard, 400));
  $('#kanban-assignee, #kanban-dept, #kanban-team, #kanban-due')
    .on('change', loadBoard);

  function debounce(fn, wait) {
    var t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn, wait);
    };
  }

  loadBoard();
});
</script>

<style>
.kanban-placeholder { height:38px; background:#e3e8ee; border:1px dashed #b7c0cc; border-radius:3px; margin-bottom:6px; }
.kanban-card a { color:#2a3b4d; text-decoration:none; }
.kanban-card a:hover { color:#0a7bbb; }
</style>
