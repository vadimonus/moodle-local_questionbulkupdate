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
    const DO_NOT_CHANGE = 'DO_NOT_CHANGE';

    /**
     * Check permissions, extract values for update and process bulk update.
     *
     * @param object $formdata
     * @throws \coding_exception
     * @throws \required_capability_exception
     */
    public function bulk_update($formdata) {
        $categorypluscontext = $formdata->categoryandcontext;
        [$categoryid, $contextid] = explode(',', $categorypluscontext);
        $context = \context::instance_by_id($contextid);
        $onlymine = false;
        if (!has_capability('moodle/question:editall', $context)) {
            require_capability('moodle/question:editmine', $context);
            $onlymine = true;
        }
        $data = $this->data_for_update($formdata);
        $count = $this->update_questions_in_category($categoryid, $context, $formdata->includingsubcategories, $onlymine, $data);
        \core\notification::success(get_string('processed', 'qbank_bulkupdate', $count));
    }

    /**
     * Cleans form data.
     * Removes values, that are not question options.
     * Removes values, that should not be changed.
     *
     * @param object $formdata
     * @return object
     */
    protected function data_for_update($formdata) {
        $cleardata = clone $formdata;
        unset($cleardata->categoryandcontext);
        unset($cleardata->includingsubcategories);
        unset($cleardata->submitbutton);
        foreach ($cleardata as $key => $value) {
            if (self::DO_NOT_CHANGE == $value
                || !empty($formdata->{'donotupdate_' . $key})
                || $this->starts_with('donotupdate_', $key)
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
     * @param \context $context
     * @param bool $includingsubcategories
     * @param bool $onlymine
     * @param object $data
     * @return int
     */
    protected function update_questions_in_category($categoryid, $context, $includingsubcategories, $onlymine, $data) {
        global $DB, $USER;

        $conditions = [
            'category' => $categoryid,
        ];
        if ($onlymine) {
            $conditions['createdby'] = $USER->id;
        }
        $questions = $DB->get_recordset('question', $conditions);
        $count = 0;
        foreach ($questions as $question) {
            $this->update_question($question, $data, $context);
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
            $count += $this->update_questions_in_category($subcategory->id, $context, $includingsubcategories, $onlymine, $data);
        }
        return $count;
    }

    /**
     * Updates single question
     *
     * @param object $question
     * @param object $data
     * @param \context $context
     * @return void
     */
    protected function update_question($question, $data, $context) {
        global $CFG, $DB, $USER;

        $modified = false;
        foreach ($data as $key => $value) {
            if (property_exists($question, $key)) {
                $question->$key = $value;
                $modified = true;
            }
        }

        $optionsmodified = $this->update_question_options($question, $data);
        $modified = $modified || $optionsmodified;

        if ($modified) {
            $question->timemodified = time();
            $question->modifiedby = $USER->id;
            // Give the question a unique version stamp determined by question_hash().
            $question->version = question_hash($question);
            $DB->update_record('question', $question);
            // Purge this question from the cache.
            \question_bank::notify_question_edited($question->id);
            // Trigger event.
            if ($CFG->version >= 2019052000.00) { // Moodle 3.7.
                $event = \core\event\question_updated::create_from_question_instance($question, $context);
                $event->trigger();
            }
        }
    }

    /**
     * Updates values in question options table
     *
     * @param object $question
     * @param object $data
     * @return bool true if options modified
     */
    protected function update_question_options($question, $data) {
        switch ($question->qtype) {
            case 'multichoice':
                return $this->update_multichoice_options($question, $this->data_for_options_update($data, 'multichoice'));
            default:
                return false;
        }
    }

    /**
     * Extracts values, intended for specified question type
     *
     * @param object $formdata
     * @param string $qtype
     * @return object
     */
    protected function data_for_options_update($formdata, $qtype) {
        $optionsdata = new \stdClass();
        foreach ($formdata as $key => $value) {
            if (!$this->starts_with($key, $qtype . '_')) {
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
    protected function starts_with($haystack, $needle) {
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
    protected function update_multichoice_options($question, $data) {
        return $this->update_generic_options_table($question, $data, 'qtype_multichoice_options');
    }

    /**
     * Updates question options in generic options table
     *
     * @param object $question
     * @param object $data
     * @param string $table
     * @throws \dml_exception
     */
    protected function update_generic_options_table($question, $data, $table) {
        global $DB;
        $options = $DB->get_record($table, ['questionid' => $question->id]);
        foreach ($data as $key => $value) {
            if (property_exists($options, $key)) {
                $options->$key = $value;
                $modified = true;
            }
        }
        if ($modified) {
            $DB->update_record($table, $options);
        }
        return $modified;
    }
}
