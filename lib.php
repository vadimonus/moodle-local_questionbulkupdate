<?php
// This file is part of Moodle - http://moodle.org/
//
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
 * Tool for questions bulk update.
 *
 * @package    qbank_bulkupdate
 * @copyright  2021 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Adds menu item into question bank on front page.
 *
 * @param navigation_node $coursenode navigation node object
 * @param stdClass $course frontpage course object
 * @param context $context frontpage course context object
 */
function qbank_bulkupdate_extend_navigation_frontpage(navigation_node $coursenode, stdClass $course,
        context $context) {
    qbank_bulkupdate_extend_navigation_course($coursenode, $course, $context);
}

/**
 * Adds menu item into question bank on course page.
 *
 * @param navigation_node $coursenode navigation node object
 * @param stdClass $course course object
 * @param context $context course context object
 */
function qbank_bulkupdate_extend_navigation_course(navigation_node $coursenode, stdClass $course,
        context $context) {
    if (!has_capability('moodle/question:editall', $context)
        && !has_capability('moodle/question:editmine', $context)
    ) {
        return;
    }
    /** @var navigation_node|null $questionbank */
    $questionbank = null;
    foreach ($coursenode->children as $node) {
        if ($node->text == get_string('questionbank', 'question')) {
            $questionbank = $node;
            break;
        }
    }
    if (!$questionbank) {
        return;
    }
    $url = new moodle_url('/question/bank/bulkupdate/bulkupdate.php', array('courseid' => $context->instanceid));
    $questionbank->add(
        get_string('navandheader', 'qbank_bulkupdate'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'questionbulkupdate'
    );
}

/**
 * Adds menu item into question bank in quiz module.
 *
 * @param navigation_node $nav navigation node object
 * @param context $context course context object
 */
function qbank_bulkupdate_extend_settings_navigation(navigation_node $nav, context $context) {
    if (!has_capability('moodle/question:editall', $context)
        && !has_capability('moodle/question:editmine', $context)
    ) {
        return;
    }
    if ($context->contextlevel != CONTEXT_MODULE) {
        return;
    }
    $parentnode = $nav->get('modulesettings');
    /** @var navigation_node|null $questionbank */
    $questionbank = null;
    foreach ($parentnode->children as $node) {
        if ($node->text == get_string('questionbank', 'question')) {
            $questionbank = $node;
            break;
        }
    }
    if (!$questionbank) {
        return;
    }
    $url = new moodle_url('/question/bank/bulkupdate/bulkupdate.php', array('cmid' => $context->instanceid));
    $questionbank->add(
        get_string('navandheader', 'qbank_bulkupdate'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'questionbulkupdate'
    );
}
