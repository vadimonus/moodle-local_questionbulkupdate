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
 * @package    local_questionbulkupdate
 * @copyright  2021 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questionbulkupdate;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

/**
 * Form for selecting category and question options.
 *
 * @package    local_questionbulkupdate
 * @copyright  2021 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends \moodleform {

    /**
     * @var string
     */
    protected $strdonotchange;
    /**
     * @var array
     */
    protected $yesnodonotchange;

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $context = $this->_customdata['context'];

        // Select category
        $this->definition_select_category($mform, $context);

        $this->strdonotchange = get_string('donotupdate', 'local_questionbulkupdate');
        $this->yesnodonotchange = [
            helper::DO_NOT_CHANGE => $this->strdonotchange,
            0 => get_string('no'),
            1 => get_string('yes')
        ];

        // Common question options
        $this->definition_common($mform);

        // Multichoice options
        $this->definition_multichoice($mform);

        // Action buttons
        $this->add_action_buttons(true, get_string('updatequestions', 'local_questionbulkupdate'));
    }

    /**
     * @param \MoodleQuickForm $mform
     * @param \context $context
     * @throws \coding_exception
     */
    protected function definition_select_category(\MoodleQuickForm $mform, $context) {
        $mform->addElement('header', 'header', get_string('selectcategoryheader', 'local_questionbulkupdate'));

        $qcontexts = new \question_edit_contexts($context);
        $contexts = $qcontexts->having_one_cap([
            'moodle/question:editall',
            'moodle/question:editmine'
        ]);

        $options = array();
        $options['contexts'] = $contexts;
        $options['top'] = true;
        $mform->addElement('questioncategory', 'categoryandcontext', get_string('category', 'question'), $options);

        $mform->addElement(
            'advcheckbox',
            'includingsubcategories',
            get_string('includingsubcategories', 'qtype_random'),
            null,
            null,
            [0, 1]
        );
    }

    /**
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function definition_common($mform) {
        global $OUTPUT;

        $mform->addElement('header', 'header', get_string('commonoptionsheader', 'local_questionbulkupdate'));

        $elements = [];
        $elements[] = $mform->createElement(
            'float',
            'defaultmark',
            get_string('defaultmark', 'question'),
            ['size' => 7]
        );
        $mform->disabledIf('defaultmark', 'donotupdate_defaultmark', 'checked');
        $elements[] = $mform->createElement(
            'checkbox',
            'donotupdate_defaultmark',
            get_string('donotupdate', 'local_questionbulkupdate')
        );
        $mform->setDefault('donotupdate_defaultmark', true);
        $mform->addGroup($elements, null, get_string('defaultmark', 'question'));

        $penaltyoptions = [helper::DO_NOT_CHANGE => $this->strdonotchange];
        foreach ([1.0000000, 0.5000000, 0.3333333, 0.2500000, 0.2000000, 0.1000000, 0.0000000] as $penalty) {
            $penaltyoptions["{$penalty}"] = (100 * $penalty) . '%';
        }
        $mform->addElement(
            'select',
            'penalty',
            get_string('penaltyforeachincorrecttry', 'question'),
            $penaltyoptions);
        $mform->setDefault('penalty', -1);
    }

    /**
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function definition_multichoice($mform) {
        $mform->addElement('header', 'header', get_string('pluginname', 'qtype_multichoice'));

        $mform->addElement(
            'select',
            'multichoice_shuffleanswers',
            get_string('shuffleanswers', 'qtype_multichoice'),
            $this->yesnodonotchange
        );
        $mform->setDefault('multichoice_shuffleanswers', -1);

        $mform->addElement(
            'select',
            'multichoice_answernumbering',
            get_string('answernumbering', 'qtype_multichoice'),
            array_merge(
                [-1 => get_string('donotupdate', 'local_questionbulkupdate')],
                \qtype_multichoice::get_numbering_styles()
            )
        );
        $mform->setDefault('multichoice_answernumbering', -1);

        if (\get_component_version('qtype_multichoice') >= 2020041600) {
            $mform->addElement(
                'select',
                'multichoice_showstandardinstruction',
                get_string('showstandardinstruction', 'qtype_multichoice'),
                $this->yesnodonotchange
            );
            $mform->setDefault('multichoice_showstandardinstruction', -1);
        }
    }
}
