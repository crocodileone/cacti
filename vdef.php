<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2012 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');
include_once('./lib/utility.php');
include_once('./lib/vdef.php');

$vdef_actions = array(
	'1' => __('Delete'),
	'2' => __('Duplicate')
);

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		vdef_form_save();

		break;
	case 'actions':
		vdef_form_actions();

		break;
	case 'item_remove_confirm':
		vdef_item_remove_confirm();

		break;
	case 'item_remove':
		vdef_item_remove();

		break;
	case 'item_edit':
		top_header();
		vdef_item_edit();
		bottom_footer();

		break;
	case 'edit':
		top_header();

		vdef_edit();

		bottom_footer();

		break;
	case 'ajax_dnd':
		vdef_item_dnd();

		break;
	default:
		top_header();

		vdef();

		bottom_footer();

		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_vdef_preview($vdef_id) {
	?>
	<tr class='even'>
		<td style='padding:4px'>
			<pre>vdef=<?php print get_vdef($vdef_id, true);?></pre>
		</td>
	</tr>
	<?php
}

/* --------------------------
    The Save Function
   -------------------------- */

function vdef_form_save() {
	if (isset_request_var('save_component_vdef')) {
		$save['id']   = get_filter_request_var('id');
		$save['hash'] = get_hash_vdef(get_request_var('id'));
		$save['name'] = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);

		if (!is_error_message()) {
			$vdef_id = sql_save($save, 'vdef');

			if ($vdef_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: vdef.php?action=edit&header=false&id=' . (empty($vdef_id) ? get_request_var('id') : $vdef_id));
	}elseif (isset_request_var('save_component_item')) {
		$sequence = get_sequence(get_filter_request_var('id'), 'sequence', 'vdef_items', 'vdef_id=' . get_filter_request_var('vdef_id'));

		$save['id']       = get_filter_request_var('id');
		$save['hash']     = get_hash_vdef(get_request_var('id'), 'vdef_item');
		$save['vdef_id']  = get_filter_request_var('vdef_id');
		$save['sequence'] = $sequence;
		$save['type']     = get_nfilter_request_var('type');
		$save['value']    = get_nfilter_request_var('value');

		if (!is_error_message()) {
			$vdef_item_id = sql_save($save, 'vdef_items');

			if ($vdef_item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: vdef.php?action=item_edit&header=false&vdef_id=' . get_request_var('vdef_id') . '&id=' . (empty($vdef_item_id) ? get_request_var('id') : $vdef_item_id));
		}else{
			header('Location: vdef.php?action=edit&header=false&id=' . get_request_var('vdef_id'));
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function vdef_form_actions() {
	global $vdef_actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') === '1') { /* delete */
				/* do a referential integrity check */
				if (sizeof($selected_items)) {
				foreach($selected_items as $vdef_id) {
					/* ================= input validation ================= */
					input_validate_input_number($vdef_id);
					/* ==================================================== */

					$vdef_ids[] = $vdef_id;
				}
				}

				if (isset($vdef_ids)) {
					db_execute('DELETE FROM vdef WHERE ' . array_to_sql_or($vdef_ids, 'id'));
					db_execute('DELETE FROM vdef_items WHERE ' . array_to_sql_or($vdef_ids, 'vdef_id'));
				}
			}elseif (get_nfilter_request_var('drp_action') === '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					duplicate_vdef($selected_items[$i], get_nfilter_request_var('title_format'));
				}
			}
		}

		header('Location: vdef.php?header=false');

		exit;
	}

	/* setup some variables */
	$vdef_list = '';

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$vdef_list .= '<li>' . db_fetch_cell('select name from vdef where id=' . $matches[1]) . '</li>';
			$vdef_array[] = $matches[1];
		}
	}

	top_header();

	form_start('vdef.php', 'vdef_actions');

	html_start_box($vdef_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($vdef_array)) {
		if (get_nfilter_request_var('drp_action') === '1') { /* delete */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following VDEF.', 'Click \'Continue\' to delete following VDEFs.', sizeof($vdef_array)) . "</p>
						<p><ul>$vdef_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete VDEF(s)'>";
		}elseif (get_nfilter_request_var('drp_action') === '2') { /* duplicate */
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to duplicate the following VDEF. You can optionally change the title format for the new VDEF.', 'Click \'Continue\' to duplicate following VDEFs. You can optionally change the title format for the new VDEFs.', sizeof($vdef_array)) . "</p>
						<p><ul>$vdef_list</ul></p>
						<p><strong>" . __('Title Format:') . "</strong><br>"; form_text_box("title_format", "<vdef_title> (1)", "", "255", "30", "text"); print "</p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='Duplicate VDEF(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one VDEF.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

    print "<tr>
        <td class='saveRow'>
            <input type='hidden' name='action' value='actions'>
            <input type='hidden' name='selected_items' value='" . (isset($vdef_array) ? serialize($vdef_array) : '') . "'>
            <input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
            $save_html
        </td>
    </tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    VDEF Item Functions
   -------------------------- */

function vdef_item_remove_confirm() {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	form_start('vdef.php');

	html_start_box('', '100%', '', '3', 'center', '');

	$vdef       = db_fetch_row('SELECT * FROM vdef WHERE id=' . get_request_var('id'));
	$vdef_item  = db_fetch_row('SELECT * FROM vdef_items WHERE id=' . get_request_var('vdef_id'));

	?>
	<tr>
		<td class='topBoxAlt'>
			<p><?php print __('Click \'Continue\' to delete the following VDEF\'s.'); ?></p>
			<p>VDEF Name: '<?php print $vdef['name'];?>'<br>
			<em><?php $vdef_item_type = $vdef_item['type']; print $vdef_item_types[$vdef_item_type];?></em>: <strong><?php print get_vdef_item_name($vdef_item['id']);?></strong></p>
		</td>
	</tr>
	<tr>
		<td align='right'>
			<input id='cancel' type='button' value='<?php print __('Cancel');?>' onClick='$("#cdialog").dialog("close");' name='cancel'>
			<input id='continue' type='button' value='<?php print __('Continue');?>' name='continue' title='<?php print __('Remove VDEF Item');?>'>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#cdialog').dialog();
	});

	$('#continue').click(function(data) {
		$.post('vdef.php?action=item_remove', { 
			__csrf_magic: csrfMagicToken, 
			vdef_id: <?php print get_request_var('vdef_id');?>, 
			id: <?php print get_request_var('id');?> 
		}, function(data) {
			$('#cdialog').dialog('close');
			loadPageNoHeader('vdef.php?action=edit&header=false&id=<?php print get_request_var('id');?>');
		});
	});
	</script>
	<?php
}
		
function vdef_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('vdef_id');
	/* ==================================================== */

	db_execute('DELETE FROM vdef_items WHERE id=' . get_request_var('vdef_id'));
}

function vdef_item_edit() {
	global $vdef_functions, $vdef_item_types, $custom_vdef_data_source_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('vdef_id');
	get_filter_request_var('type_select');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$vdef = db_fetch_row_prepared('SELECT * FROM vdef_items WHERE id = ?', array(get_request_var('id')));
		$current_type = $vdef['type'];
		$values[$current_type] = $vdef['value'];
	}

	print '<div>';
	draw_vdef_preview(get_request_var('vdef_id'));
	print '</div>';

	if (!isempty_request_var('vdef_id')) {
		$header_label = __('VDEF Items [edit: %s]', db_fetch_cell_prepared('SELECT name FROM vdef WHERE id = ?', array(get_request_var('vdef_id'))) );
	}else {
		$header_label = __('VDEF Items [new]');
	}

	form_start('vdef.php', 'form_vdef');

	html_start_box( $header_label, '100%', '', '3', 'center', '');

	if (isset_request_var('type_select')) {
		$current_type = get_request_var('type_select');
	}elseif (isset($vdef['type'])) {
		$current_type = $vdef['type'];
	}else{
		$current_type = CVDEF_ITEM_TYPE_FUNCTION;
	}

	form_alternate_row();
	print '<td width="50%"><font class="textEditTitle">' . __('VDEF Item Type') . '</font><br>'	. __('Choose what type of VDEF item this is.') .'</td>';
	?>
		<td>
			<select id='type_select'>
				<?php
				while (list($var, $val) = each($vdef_item_types)) {
					print "<option value='" . htmlspecialchars('vdef.php?action=item_edit' . (isset_request_var('id') ? '&id=' . get_request_var('id') : '') . '&vdef_id=' . get_request_var('vdef_id') . '&type_select=' . $var) . "'"; if ($var == $current_type) { print ' selected'; } print ">$val</option>\n";
				}
				?>
			</select>
			<script type='text/javascript'>
			$(function() {
				$('#type_select').change(function() {
					loadPageNoHeader('vdef.php?action=item_edit&header=false&vdef_id=<?php print get_request_var('vdef_id');?>&type_select='+$('#type_select').val())
				});
			});
			</script>
		</td>
	<?php
	form_end_row();

	form_alternate_row();
	print '<td width="50%"><font class="textEditTitle">' . __('VDEF Item Value') . '</font><br>' . __('Enter a value for this VDEF item.') . '</td>';
	?>
		<td>
			<?php
			switch ($current_type) {
			case '1':
				form_dropdown('value', $vdef_functions, '', '', (isset($vdef['value']) ? $vdef['value'] : ''), '', '');
				break;
			case '4':
				form_dropdown('value', $custom_vdef_data_source_types, '', '', (isset($vdef['value']) ? $vdef['value'] : ''), '', '');
				break;
			case '6':
				form_text_box('value', (isset($vdef['value']) ? $vdef['value'] : ''), '', '255', 30, 'text', (isset_request_var('id') ? get_request_var('id') : '0'));
				break;
			}
			?>
		</td>
	<?php
	form_end_row();

	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('type', $current_type, '');
	form_hidden_box('vdef_id', get_request_var('vdef_id'), '');
	form_hidden_box('save_component_item', '1', '');

	html_end_box();

	form_save_button('vdef.php?action=edit&id=' . get_request_var('vdef_id'));
}

/* ---------------------
    VDEF Functions
   --------------------- */

function vdef_item_dnd() {
	/* ================= Input validation ================= */
	get_filter_request_var('id');
	/* ================= Input validation ================= */

	if (!isset_request_var('vdef_item') || !is_array(get_request_var('vdef_item'))) exit;

	/* vdef table contains one row defined as 'nodrag&nodrop' */
	unset($_REQUEST['vdef_item'][0]);

	/* delivered vdef ids has to be exactly the same like we have stored */
	$old_order = array();

	foreach(get_request_var('vdef_item') as $sequence => $vdef_id) {
		if (empty($vdef_id)) continue;
		$new_order[$sequence] = str_replace('line', '', $vdef_id);
	}

	$vdef_items = db_fetch_assoc_prepared('SELECT id, sequence FROM vdef_items WHERE vdef_id = ?', array(get_request_var('id')));

	if(sizeof($vdef_items)) {
		foreach($vdef_items as $item) {
			$old_order[$item['sequence']] = $item['id'];
		}
	}else {
		exit;
	}

	if(sizeof(array_diff($new_order, $old_order))>0) exit;

	/* the set of sequence numbers has to be the same too */
	if(sizeof(array_diff_key($new_order, $old_order))>0) exit;
	/* ==================================================== */

	foreach($new_order as $sequence => $vdef_id) {
		input_validate_input_number($sequence);
		input_validate_input_number($vdef_id);

		db_execute_prepared('UPDATE vdef_items SET sequence = ? WHERE id = ?', array($sequence, $vdef_id));
	}

	header('Location: vdef.php?action=edit&header=false&id=' . get_request_var('id'));
}

function vdef_edit() {
	global $vdef_item_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$vdef = db_fetch_row('SELECT * FROM vdef WHERE id=' . get_request_var('id'));
		$header_label = __('VDEFs [edit: %s]', $vdef['name']);
	}else{
		$header_label = __('VDEFs [new]');
	}

	form_start('vdef.php', 'vdef_edit');

	html_start_box( $header_label, '100%', '', '3', 'center', '');

	$preset_vdef_form_list = preset_vdef_form_list();
	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($preset_vdef_form_list, (isset($vdef) ? $vdef : array()))
		)
	);

	html_end_box();

	form_hidden_box('id', (isset($vdef['id']) ? $vdef['id'] : '0'), '');
	form_hidden_box('save_component_vdef', '1', '');

	if (!isempty_request_var('id')) {
		html_start_box('', '100%', '', '3', 'center', '');
		draw_vdef_preview(get_request_var('id'));
		html_end_box();

		html_start_box('VDEF Items', '100%', '', '3', 'center', 'vdef.php?action=item_edit&vdef_id=' . $vdef['id']);
		$header_items = array(
			array('display' => __('Item'), 'align' => 'left'),
			array('display' => __('Item Value'), 'align' => 'left')
		);

		html_header($header_items, 2);

		$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ? ORDER BY sequence', array(get_request_var('id')));
		$i = 0;
		if (sizeof($vdef_items)) {
			foreach ($vdef_items as $vdef_item) {
				form_alternate_row('line' . $vdef_item['id'], true);
					?>
					<td>
						<a class='linkEditMain' href='<?php print htmlspecialchars('vdef.php?action=item_edit&id=' . $vdef_item['id'] . '&vdef_id=' . $vdef['id']);?>'><?php print __('Item #%d', $i);?></a>
					</td>
					<td>
						<em><?php $vdef_item_type = $vdef_item['type']; print $vdef_item_types[$vdef_item_type];?></em>: <strong><?php print get_vdef_item_name($vdef_item['id']);?></strong>
					</td>
					<td align='right' style='text-align:right'>
						<a id='<?php print $vdef['id'] . '_' . $vdef_item['id'];?>' class='delete deleteMarker fa fa-remove' title='<?php print __('Delete VDEF Item');?>'></a>
					</td>
			<?php
			form_end_row();
			$i++;
			}
		}

		html_end_box();
	}

	form_save_button('vdef.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		$('#vdef_edit3').find('.cactiTable').attr('id', 'vdef_item');
		$('body').append("<div id='cdialog'></div>");

		$('#vdef_item').tableDnD({
			onDrop: function(table, row) {
				loadPageNoHeader('vdef.php?action=ajax_dnd&id=<?php isset_request_var('id') ? print get_request_var('id') : print 0;?>&'+$.tableDnD.serialize());
			}
		});

		$('.delete').click(function (event) {
			event.preventDefault();

			id = $(this).attr('id').split('_');
			request = 'vdef.php?action=item_remove_confirm&id='+id[0]+'&vdef_id='+id[1];
			$.get(request, function(data) {
				$('#cdialog').html(data);
				applySkin();
				$('#cdialog').dialog({ title: '<?php print __('Delete VDEF Item');?>', minHeight: 80, minWidth: 500 });
			});
		}).css('cursor', 'pointer');
	});

	</script>
	<?php
}

function vdef_filter() {
	global $item_rows;

	html_start_box( __('VDEFs'), '100%', '', '3', 'center', 'vdef.php?action=edit');
	?>
	<tr class='even'>
		<td>
			<form id='form_vdef' action='vdef.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('VDEFs');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
                    <td>
                        <input type='checkbox' id='has_graphs' <?php print (get_request_var('has_graphs') == 'true' ? 'checked':'');?>>
                    </td>
                    <td>
                        <label for='has_graphs'><?php print __('Has Graphs');?></label>
                    </td>
					<td>
						<input type='button' Value='<?php print __x('filter: use', 'Go');?>' id='refresh'>
					</td>
					<td>
						<input type='button' Value='<?php print __x('filter: reset', 'Clear');?>' id='clear'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL = 'vdef.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_graphs='+$('#has_graphs').is(':checked')+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'vdef.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_graphs').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_vdef').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}

function get_vdef_records(&$total_rows, &$rowspp) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (vdef.name LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var('has_graphs') == 'true') {
		$sql_having = 'HAVING graphs>0';
	}else{
		$sql_having = '';
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(rows)
        FROM (
            SELECT vd.id AS rows,
            SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
            FROM vdef AS vd
            LEFT JOIN graph_templates_item AS gti
            ON gti.vdef_id=vd.id
            $sql_where
            GROUP BY vd.id
            $sql_having
        ) AS rs");

	return db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates,
        SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
        FROM (
            SELECT vd.*, gti.local_graph_id
            FROM vdef AS vd
            LEFT JOIN graph_templates_item AS gti
            ON gti.vdef_id=vd.id
            GROUP BY vd.id, gti.graph_template_id, gti.local_graph_id
        ) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);
}

function vdef($refresh = true) {
	global $vdef_actions;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_vdef');
	/* ================= input validation ================= */

	vdef_filter();

	$total_rows = 0;
	$vdefs = array();
	$rows  = get_request_var('rows');

	$vdefs = get_vdef_records($total_rows, $rows);

	form_start('vdef.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('vdef.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 5, 'VDEFs', 'page', 'main');

    print $nav;

    $display_text = array(
        'name'      => array('display' => __('VDEF Name'), 'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this VDEF.') ),
        'nosort'    => array('display' => __('Deletable'), 'align' => 'right', 'tip' => __('VDEFs that are in use can not be Deleted. In use is defined as being referenced by a Graph or a Graph Template.') ),
        'graphs'    => array('display' => __('Graphs Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Graphs using this VDEF.') ),
        'templates' => array('display' => __('Templates Using'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Graphs Templates using this VDEF.') )
	);

    html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

    $i = 0;
    if (sizeof($vdefs) > 0) {
        foreach ($vdefs as $vdef) {
            if ($vdef['graphs'] == 0 && $vdef['templates'] == 0) {
                $disabled = false;
            }else{
                $disabled = true;
            }

            form_alternate_row('line' . $vdef['id'], false, $disabled);
            form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('vdef.php?action=edit&id=' . $vdef['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($vdef['name'])) : htmlspecialchars($vdef['name'])) . '</a>', $vdef['id']);
            form_selectable_cell($disabled ? __('No'):__('Yes'), $vdef['id'], '', 'text-align:right');
            form_selectable_cell(number_format($vdef['graphs']), $vdef['id'], '', 'text-align:right');
            form_selectable_cell(number_format($vdef['templates']), $vdef['id'], '', 'text-align:right');
            form_checkbox_cell($vdef['name'], $vdef['id'], $disabled);
            form_end_row();
        }
        print $nav;
    }else{
        print "<tr class='tableRow'><td colspan='4'><em>" . __('No VDEFs') . "</em></td></tr>\n";
    }
    html_end_box(false);

    /* draw the dropdown containing a list of available actions for this form */
    draw_actions_dropdown($vdef_actions);

    form_end();
}