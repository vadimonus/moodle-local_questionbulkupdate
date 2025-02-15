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

namespace qbank_bulkupdate;

use context;
use core\event\question_created;
use core\event\question_updated;
use core\notification;
use core_question\local\bank\question_version_status;
use question_bank;
use stdClass;

/**
 * Helper class for updating questions
 *
 * @package    qbank_bulkupdate
 * @copyright  2021 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Option values for select lists.
     */
    public const DO_NOT_CHANGE = 'DO_NOT_CHANGE';

    /**
     * Check permissions, extract values for update and process bulk update.
     *
     * @param object $formdata
     * @throws \coding_exception
     * @throws \required_capability_exception
     */
    public static function bulk_update($formdata) {
        $categorypluscontext = $formdata->categoryandcontext;
        [$categoryid, $contextid] = explode(',', $categorypluscontext);
        $context = context::instance_by_id($contextid);
        $onlymine = false;
        if (!has_capability('moodle/question:editall', $context)) {
            require_capability('moodle/question:editmine', $context);
            $onlymine = true;
        }
        $data = static::data_for_update($formdata);
        $count = static::update_questions_in_category($categoryid, $context, $formdata->includingsubcategories, $onlymine, $data);
        notification::success(get_string('processed', 'qbank_bulkupdate', $count));
    }

    /**
     * Cleans form data.
     * Removes values, that are not question options.
     * Removes values, that should not be changed.
     *
     * @param object $formdata
     * @return object
     */
    protected static function data_for_update($formdata) {
        $cleardata = clone $formdata;
        unset($cleardata->categoryandcontext);
        unset($cleardata->includingsubcategories);
        unset($cleardata->submitbutton);
        foreach ($cleardata as $key => $value) {
            if (self::DO_NOT_CHANGE == $value
                || !empty($formdata->{'donotupdate_' . $key})
                || static::starts_with($key, 'donotupdate_')
            ) {
                unset($cleardata->$key);
            }
        }
        return $cleardata;
    }

    /**
     * Recursively update questions in category and subcategories
     *
     * @param int $categoryid
     * @param context $context
     * @param bool $includingsubcategories
     * @param bool $onlymine
     * @param object $data
     * @return int
     */
    protected static function update_questions_in_category($categoryid, $context, $includingsubcategories, $onlymine, $data) {
        global $DB, $USER;

        $sql = "SELECT q.*
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            WHERE qbe.questioncategoryid = :categoryid
              AND qv.status IN (:statusready, :statusdraft)";
        $params = [
            'categoryid' => $categoryid,
            'statusready' => question_version_status::QUESTION_STATUS_READY,
            'statusdraft' => question_version_status::QUESTION_STATUS_DRAFT,
        ];
        if ($onlymine) {
            $sql .= " AND q.createdby = :createdby";
            $params['createdby'] = $USER->id;
        }
        $questions = $DB->get_recordset_sql($sql, $params);
        $count = 0;
        foreach ($questions as $question) {
            static::update_question($question, $data, $context, $categoryid);
            $count++;
        }
        $questions->close();

        if (!$includingsubcategories) {
            return $count;
        }
        $subcategories = $DB->get_records(
            'question_categories',
            [
                'parent' => $categoryid,
                'contextid' => $context->id,
            ],
            'name ASC'
        );
        foreach ($subcategories as $subcategory) {
            $count += static::update_questions_in_category($subcategory->id, $context, $includingsubcategories, $onlymine, $data);
        }
        return $count;
    }

    /**
     * Updates single question
     *
     * @param object $question
     * @param object $data
     * @param context $context
     * @param int $categoryid
     * @return void
     */
    protected static function update_question($question, $data, $context, $categoryid) {
        global $DB, $USER;

        $modified = false;
        $commondata = static::data_for_question_update($data);
        foreach ($commondata as $key => $value) {
            if (property_exists($question, $key) && $question->$key != $value) {
                $question->$key = $value;
                $modified = true;
            }
        }

        $optionsmodified = static::update_question_options($question, $data);
        $modified = $modified || $optionsmodified;

        if ($modified) {
            $question->timemodified = time();
            $question->modifiedby = $USER->id;
            $DB->update_record('question', $question);
            static::increase_question_version($question);
            // Purge this question from the cache.
            question_bank::notify_question_edited($question->id);
            // Trigger event.
            $question->category = $categoryid;
            $event = question_updated::create_from_question_instance($question, $context);
            $event->trigger();
        }
    }

    /**
     * Updates values in question options table
     *
     * @param object $question
     * @param object $data
     * @return bool true if options modified
     */
    protected static function update_question_options($question, $data) {
        switch ($question->qtype) {
            case 'multichoice':
                return static::update_multichoice_options($question, static::data_for_options_update($data, 'multichoice'));
            default:
                return false;
        }
    }

    /**
     * Extracts values, intended for all question types
     *
     * @param object $formdata
     * @return object
     */
    protected static function data_for_question_update($formdata) {
        $questiondata = new stdClass();
        foreach ($formdata as $key => $value) {
            if (!static::starts_with($key, 'common_')) {
                continue;
            }
            $questionkey = substr($key, strlen('common_'));
            $questiondata->$questionkey = $value;
        }
        return $questiondata;
    }

    /**
     * Extracts values, intended for specified question type
     *
     * @param object $formdata
     * @param string $qtype
     * @return object
     */
    protected static function data_for_options_update($formdata, $qtype) {
        $optionsdata = new stdClass();
        foreach ($formdata as $key => $value) {
            if (!static::starts_with($key, $qtype . '_')) {
                continue;
            }
            $optionkey = substr($key, strlen($qtype . '_'));
            $optionsdata->$optionkey = $value;
        }
        return $optionsdata;
    }

    /**
     * Check if string starts with other string
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    protected static function starts_with($haystack, $needle) {
        $length = strlen($needle);
        return substr($haystack, 0, $length) === $needle;
    }

    /**
     * Updates question options for multichoice question
     *
     * @param object $question
     * @param object $data
     * @return bool
     */
    protected static function update_multichoice_options($question, $data) {
        return static::update_generic_options_table($question, $data, 'qtype_multichoice_options');
    }

    /**
     * Updates question options in generic options table
     *
     * @param object $question
     * @param object $data
     * @param string $table
     * @throws \dml_exception
     */
    protected static function update_generic_options_table($question, $data, $table) {
        global $DB;
        $modified = false;
        $options = $DB->get_record($table, ['questionid' => $question->id]);
        foreach ($data as $key => $value) {
            if (property_exists($options, $key) && $options->$key != $value) {
                $options->$key = $value;
                $modified = true;
            }
        }
        if ($modified) {
            $DB->update_record($table, $options);
        }
        return $modified;
    }

    /**
     * Increases question version.
     *
     * @param object $question
     * @return void
     */
    protected static function increase_question_version($question) {
        global $DB;
        $questionversion = $DB->get_record('question_versions', ['questionid' => $question->id]);
        $newquestionversion = new stdClass();
        $newquestionversion->id = $questionversion->id;
        $newquestionversion->version = ($questionversion->version ?? 0) + 1;
        $DB->update_record('question_versions', $newquestionversion);
    }
}
