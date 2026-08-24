<?php
/*********************************************************************
    kanban.inc.php

    Modern Tasks Kanban Board for osTicket IDIN9 (v1.18.4.27).
    Supports 4-Lane Flow: Open -> Doing -> Verifying -> Completed.
    Server-rendered columns hydrated with AJAX; drag-and-drop powered by
    jQuery UI Sortable with real-time status & flag synchronisation.
**********************************************************************/
if(!defined('OSTSTAFFINC') || !$thisstaff) die('Unauthorized');

require_once INCLUDE_DIR.'class.kanban.php';

$columns = Kanban::getStatuses();
$depts = Dept::getActiveDepartments();
$teams = $thisstaff->getTeams();
?>

<div class="kanban-wrapper">
  <!-- Kanban Header & Filters Bar -->
  <div class="kanban-toolbar">
    <div class="kanban-toolbar-left">
      <div class="kanban-search-box">
        <input type="text" id="kanban-search" placeholder="<?php echo __('Search tasks or #...'); ?>" autocomplete="off">
      </div>
      <select id="kanban-assignee" class="kanban-select">
        <option value=""><?php echo __('All Assignees'); ?></option>
        <option value="s<?php echo $thisstaff->getId(); ?>"><?php echo Format::htmlchars($thisstaff->getName()); ?> (Me)</option>
        <?php foreach ($teams as $tid) {
          if ($team = Team::lookup($tid)) { ?>
          <option value="t<?php echo $tid; ?>"><?php echo Format::htmlchars($team->getName()); ?></option>
        <?php } } ?>
      </select>
      <select id="kanban-dept" class="kanban-select">
        <option value=""><?php echo __('All Departments'); ?></option>
        <?php foreach ($depts as $id => $name) { ?>
          <option value="<?php echo $id; ?>"><?php echo Format::htmlchars($name); ?></option>
        <?php } ?>
      </select>
      <select id="kanban-team" class="kanban-select">
        <option value=""><?php echo __('All Teams'); ?></option>
        <?php foreach ($teams as $tid) {
          if ($team = Team::lookup($tid)) { ?>
          <option value="<?php echo $tid; ?>"><?php echo Format::htmlchars($team->getName()); ?></option>
        <?php } } ?>
      </select>
      <select id="kanban-due" class="kanban-select">
        <option value=""><?php echo __('Any Due Date'); ?></option>
        <option value="overdue"><?php echo __('Overdue'); ?></option>
        <option value="today"><?php echo __('Due Today'); ?></option>
      </select>
    </div>
    <div class="kanban-toolbar-right">
      <span id="kanban-count" class="kanban-badge-total">Loading...</span>
    </div>
  </div>

  <div id="kanban-error" class="kanban-alert-banner" style="display:none;"></div>

  <!-- Kanban Board Columns Grid (4 Lanes: Open -> Doing -> Verifying -> Completed) -->
  <div id="kanban-board" class="kanban-grid">
    <?php foreach ($columns as $c) { 
      $colId = $c['id'];
    ?>
    <div class="kanban-col-container kanban-col-<?php echo $colId; ?>" data-status="<?php echo $colId; ?>">
      <div class="kanban-col-header">
        <div class="kanban-col-title-wrap">
          <span class="kanban-col-indicator"></span>
          <h3 class="kanban-col-title"><?php echo Format::htmlchars($c['label']); ?></h3>
        </div>
        <span class="kanban-col-count">(0)</span>
      </div>
      <div class="kanban-cards-container">
        <div class="kanban-cards-list" data-status="<?php echo $colId; ?>"></div>
      </div>
    </div>
    <?php } ?>
  </div>
</div>

<script type="text/javascript">
$(function() {
  if (typeof $.ui === 'undefined') {
    $('<script>').attr('src', 'js/jquery-ui-1.13.2.custom.min.js').appendTo('head');
  }

  function cardHtml(card) {
    var overdueBadge = card.isoverdue ? '<span class="kanban-tag kanban-tag-overdue">Overdue</span>' : '';
    var assignee = card.assignee ? card.assignee.name : '<?php echo __('Unassigned'); ?>';
    var deptBadge = card.dept ? '<span class="kanban-tag kanban-tag-dept">' + $('<div>').text(card.dept).html() + '</span>' : '';
    var dueText = card.due ? '<span class="kanban-card-due"><i class="icon-calendar"></i> ' + $('<div>').text(card.due).html() + '</span>' : '';

    return ''
      + '<div class="kanban-card" data-id="' + card.id + '">'
      + '  <div class="kanban-card-header">'
      + '    <a href="' + card.url + '" class="kanban-card-number">#' + $('<div>').text(card.number).html() + '</a>'
      + '    <div class="kanban-card-tags">' + deptBadge + overdueBadge + '</div>'
      + '  </div>'
      + '  <div class="kanban-card-title">'
      + '    <a href="' + card.url + '" class="preview">' + $('<div>').text(card.title || 'No Subject').html() + '</a>'
      + '  </div>'
      + '  <div class="kanban-card-footer">'
      + '    <div class="kanban-card-assignee"><i class="icon-user"></i> ' + $('<div>').text(assignee).html() + '</div>'
      + '    ' + dueText
      + '  </div>'
      + '</div>';
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
      $('.kanban-cards-list').empty();
      var total = 0;
      if (data && data.board) {
        $.each(data.board, function (status, col) {
          var $col = $('.kanban-col-container[data-status="' + status + '"]');
          if (!$col.length) return;
          var count = 0;
          if (col.cards && col.cards.length) {
            $.each(col.cards, function (i, card) {
              $col.find('.kanban-cards-list').append(cardHtml(card));
              total++;
              count++;
            });
          }
          $col.find('.kanban-col-count').text('(' + count + ')');
        });
      }
      $('#kanban-count').text(total + ' <?php echo __('Tasks'); ?>');
      enableSortable();
    }).fail(function (xhr) {
      $('#kanban-error').text(xhr.responseText || '<?php echo __('Unable to load Kanban board'); ?>').show();
    });
  }

  function enableSortable() {
    $('.kanban-cards-list').sortable({
      connectWith: '.kanban-cards-list',
      items: '.kanban-card',
      placeholder: 'kanban-card-placeholder',
      cursor: 'grabbing',
      opacity: 0.85,
      revert: 150,
      receive: function (event, ui) {
        var $card = ui.item;
        var $column = $(this).closest('.kanban-col-container');
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
          $('#kanban-error').text(xhr.responseText || '<?php echo __('Unable to update task status'); ?>').show();
          loadBoard();
        });
      }
    });
  }

  $('#kanban-search').on('keyup', debounce(loadBoard, 300));
  $('#kanban-assignee, #kanban-dept, #kanban-team, #kanban-due').on('change', loadBoard);

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
/* Modern Kanban Board Layout */
.kanban-wrapper {
  margin: 15px 0 30px 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Toolbar & Filters */
.kanban-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #ffffff;
  padding: 14px 18px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 20px;
}

.kanban-toolbar-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.kanban-search-box input {
  height: 36px;
  padding: 6px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  width: 200px;
  background: #f8fafc;
  transition: all 0.2s ease;
}

.kanban-search-box input:focus {
  outline: none;
  border-color: #3b82f6;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.kanban-select {
  height: 36px;
  padding: 6px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  background: #f8fafc;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s ease;
}

.kanban-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.kanban-badge-total {
  background: #e2e8f0;
  color: #475569;
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Alert Banner */
.kanban-alert-banner {
  background: #fee2e2;
  border-left: 4px solid #ef4444;
  color: #991b1b;
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
  font-size: 13px;
}

/* Grid Columns: 4 Lanes */
.kanban-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  align-items: start;
  width: 100%;
}

@media (max-width: 1200px) {
  .kanban-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 650px) {
  .kanban-grid {
    grid-template-columns: 1fr;
  }
}

.kanban-col-container {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px;
  min-height: 480px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.kanban-col-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e2e8f0;
}

.kanban-col-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.kanban-col-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.kanban-col-open .kanban-col-indicator { background: #0284c7; box-shadow: 0 0 6px rgba(2, 132, 199, 0.4); }
.kanban-col-doing .kanban-col-indicator { background: #d97706; box-shadow: 0 0 6px rgba(217, 119, 6, 0.4); }
.kanban-col-verifying .kanban-col-indicator { background: #7c3aed; box-shadow: 0 0 6px rgba(124, 58, 237, 0.4); }
.kanban-col-closed .kanban-col-indicator { background: #059669; box-shadow: 0 0 6px rgba(5, 150, 105, 0.4); }

.kanban-col-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
}

.kanban-col-count {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  background: #e2e8f0;
  padding: 2px 8px;
  border-radius: 12px;
}

.kanban-cards-container {
  flex: 1;
}

.kanban-cards-list {
  min-height: 120px;
  height: 100%;
}

/* Card Styling */
.kanban-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  cursor: grab;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  border-color: #cbd5e1;
}

.kanban-card:active {
  cursor: grabbing;
}

.kanban-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.kanban-card-number {
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
}

.kanban-card-number:hover {
  text-decoration: underline;
}

.kanban-card-tags {
  display: flex;
  gap: 4px;
}

.kanban-tag {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.kanban-tag-dept {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.kanban-tag-overdue {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.kanban-card-title {
  margin-bottom: 10px;
  line-height: 1.4;
}

.kanban-card-title a {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  text-decoration: none;
}

.kanban-card-title a:hover {
  color: #2563eb;
}

.kanban-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: #64748b;
  border-top: 1px solid #f1f5f9;
  padding-top: 8px;
}

.kanban-card-assignee {
  display: flex;
  align-items: center;
  gap: 4px;
}

.kanban-card-due {
  font-size: 11px;
  color: #64748b;
}

/* Drag & Drop Placeholder */
.kanban-card-placeholder {
  background: rgba(148, 163, 184, 0.12);
  border: 2px dashed #94a3b8;
  border-radius: 8px;
  margin-bottom: 10px;
  min-height: 70px;
}
</style>
