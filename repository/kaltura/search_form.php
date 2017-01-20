<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kaltura video assignment grade preferences form
 *
 * @package    Repository
 * @subpackage Kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This function prints the search form for Moodle 2.3 and beyond installs.  The
 * only difference between this function and (@see
 * repository_kaltura_print_search_form()) is that this form does only uses one
 * text box to search through video name and text.  And it does not give the
 * user the option to filter searches via course name
 *
 * @param obj - objec 'id' = repo id, 'context'->'id' = context id
 *
 * @return HTML markup
 */
function repository_kaltura_print_new_search_form($data) {
    $html = '';

    // Hidden field repo instance id
    $attributes = array('type'=>'hidden',
                        'name' => 'repo_id',
                        'value' => $data->id);
    $html .= html_writer::empty_tag('input', $attributes);

    // hidden field context id
    $attributes['name'] = 'ctx_id';
    $attributes['value'] = $data->context->id;
    $html .= html_writer::empty_tag('input', $attributes);

    // hidden field session key
    $attributes['name'] = 'sesskey';
    $attributes['value'] = sesskey();
    $html .= html_writer::empty_tag('input', $attributes);

    $title = get_string('search_name_tooltip', 'repository_kaltura');

    // text field search name
    $attributes['type'] = 'text';
    $attributes['name'] = 's';
    $attributes['value'] = '';
    $attributes['title'] = $title;
    $html .= html_writer::empty_tag('input', $attributes);

    return $html;

}

/**
 * This function prints the search form for pre Moodle 2.3 installs
 *
 * @param object - object 'id' = repo id, 'context'->'id' = context id
 *
 * @return HTML markup
 */
function repository_kaltura_print_search_form($data) {

    $html = '';

    // Hidden field repo instance id
    $attributes = array('type'=>'hidden',
                        'name' => 'repo_id',
                        'value' => $data->id);
    $html .= html_writer::empty_tag('input', $attributes);

    // hidden field context id
    $attributes['name'] = 'ctx_id';
    $attributes['value'] = $data->context->id;
    $html .= html_writer::empty_tag('input', $attributes);

    // hidden field session key
    $attributes['name'] = 'sesskey';
    $attributes['value'] = sesskey();
    $html .= html_writer::empty_tag('input', $attributes);

    // label search name
    $param = array('for' => 'label_search_name');
    $title = get_string('search_name', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);
    $html .= html_writer::empty_tag('br');

    // text field search name
    $attributes['type'] = 'text';
    $attributes['name'] = 's';
    $attributes['value'] = '';
    $attributes['title'] = $title;
    $html .= html_writer::empty_tag('input', $attributes);
    $html .= html_writer::empty_tag('br');
    $html .= html_writer::empty_tag('br');

    // label search tags
    $param = array('for' => 'label_search_tags');
    $title = get_string('search_tags', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);
    $html .= html_writer::empty_tag('br');

    // textfield search tags
    $attributes['type'] = 'text';
    $attributes['name'] = 't';
    $attributes['value'] = '';
    $attributes['title'] = $title;
    $html .= html_writer::empty_tag('input', $attributes);
    $html .= html_writer::empty_tag('br');
    $html .= html_writer::empty_tag('br');

    // label course name filter
    $param = array('for' => 'label_course_filter');
    $title = get_string('course_filter', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);
    $html .= html_writer::empty_tag('br');

    // select course name filter options
    $options = array('contains' => get_string('contains', 'repository_kaltura'),
                     'equals' => get_string('equals', 'repository_kaltura'),
                     'startswith' => get_string('startswith', 'repository_kaltura'),
                     'endswith' => get_string('endswith', 'repository_kaltura')
                    );
    $html .= html_writer::select($options, 'course_with', '', false, array('title' => get_string('course_filter_select_title', 'repository_kaltura')));

    $html .= '&nbsp';

    // text field course name filter
    $attributes['type'] = 'text';
    $attributes['name'] = 'c';
    $attributes['value'] = '';
    $attributes['id'] = 'course_name_filter';
    $attributes['title'] = $title;
    $html .= html_writer::empty_tag('input', $attributes);
    $html .= html_writer::empty_tag('br');
    $html .= html_writer::empty_tag('br');

    return $html;
}

/**
 * Prints required hidden element for users having the course video visibility
 * capability
 *
 * @param bool - this needs to be set to false for Moodle versions 2.3 and
 * beyond because of this bug: http://tracker.moodle.org/browse/MDL-35274
 *
 * @return HTML markup
 */
function repository_kaltura_print_used_selection($enable_javascript = true) {
    $html = '';

    $param = array('for' => 'label_shared_or_used');
    $title = get_string('search_shared_or_used', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);

    $html .= '&nbsp';

    // select type of search to perform
    $options = array('used'       => get_string('search_used', 'repository_kaltura'),
                     'own'          => get_string('search_own_upload', 'repository_kaltura')
                    );

    if ($enable_javascript) {
        $javascript_event = 'var share_select = document.getElementById("menushared_used");
                             if (1 == share_select.selectedIndex) {
                                 document.getElementById("course_name_filter").disabled = true;
                                 document.getElementById("menucourse_with").disabled = true;
                             } else {
                                 document.getElementById("course_name_filter").disabled = false;
                                 document.getElementById("menucourse_with").disabled = false;
                             }
                            ';
    } else {
        $javascript_event = '';
    }


    $html .= html_writer::select($options, 'shared_used', 'own', false, array('onclick' => $javascript_event, 'title' => $title));

    return $html;

}

/**
 * Prints a drop down selection for users having both the course video
 * visibility and shared video visibility capabilities
 *
 * @param bool - this needs to be set to false for Moodle versions 2.3 and
 * beyond because of this bug: http://tracker.moodle.org/browse/MDL-35274
 *
 * @return HTML markup
 */
function repository_kaltura_print_shared_used_selection($enable_javascript = true) {
    $html = '';

    // label type of search
    $param = array('for' => 'label_shared_or_used');
    $title = get_string('search_shared_or_used', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);

    $html .= '&nbsp';

    // select type of search to perform
    $options = array('shared'       => get_string('search_shared', 'repository_kaltura'),
                     'site_shared'  => get_string('search_site_shared', 'repository_kaltura'),
                     'used'         => get_string('search_used', 'repository_kaltura'),
                     'own'          => get_string('search_own_upload', 'repository_kaltura')
                    );

    if ($enable_javascript) {
        $javascript_event = 'var share_select = document.getElementById("menushared_used");
                             if (1 == share_select.selectedIndex || 3 == share_select.selectedIndex) {
                                 document.getElementById("course_name_filter").disabled = true;
                                 document.getElementById("menucourse_with").disabled = true;
                             } else {
                                 document.getElementById("course_name_filter").disabled = false;
                                 document.getElementById("menucourse_with").disabled = false;
                             }
                            ';
    } else {
        $javascript_event = '';
    }

    $html .= html_writer::select($options, 'shared_used', 'own', false, array('onclick' => $javascript_event, 'title' => $title));

    return $html;
}

/**
 * Prints a drop down selection for users having the shared video visibility
 * capability
 *
 * @param bool - this needs to be set to false for Moodle versions 2.3 and
 * beyond because of this bug: http://tracker.moodle.org/browse/MDL-35274
 *
 * @return HTML markup
 */
function repository_kaltura_print_shared_selection($enable_javascript = true) {
    $html = '';

    $param = array('for' => 'label_shared_or_used');
    $title = get_string('search_shared_or_used', 'repository_kaltura');
    $html .= html_writer::tag('label', $title, $param);

    $html .= '&nbsp';

    // select type of search to perform
    $options = array('shared'       => get_string('search_shared', 'repository_kaltura'),
                     'site_shared'  => get_string('search_site_shared', 'repository_kaltura'),
                     'own'          => get_string('search_own_upload', 'repository_kaltura')
                    );

    if ($enable_javascript) {
        $javascript_event = 'var share_select = document.getElementById("menushared_used");
                             if (1 == share_select.selectedIndex || 2 == share_select.selectedIndex) {
                                 document.getElementById("course_name_filter").disabled = true;
                                 document.getElementById("menucourse_with").disabled = true;
                             } else {
                                 document.getElementById("course_name_filter").disabled = false;
                                 document.getElementById("menucourse_with").disabled = false;
                             }
                            ';
    } else {
        $javascript_event = '';
    }


    $html .= html_writer::select($options, 'shared_used', 'own', false, array('onclick' => $javascript_event, 'title' => $title));

    return $html;
}

/**
 * This function prints javascript used to hide and unhide the search form for
 * Moodle version 2.3 and above
 */
function repository_kaltura_print_search_form_javascript() {
    $javascript_event = '
    // Change the style of the file picker search bar to resolve MDL-35233
    var search_bar = document.getElementById("kal_repo_search").parentNode.parentNode;

    if (search_bar.style.display != "table-row") {
        search_bar.style.display = "table-row";
    }

    var search_form = document.getElementById("kal_repo_search")
    if (search_form) {
        if ("none" == search_form.style.display) {
            search_form.style.display = "block";
        } else {
            search_form.style.display = "none";
        }
    };';

    return $javascript_event;
}