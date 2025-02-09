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
 * @copyright  2025 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_bulkupdate;

use core_question\local\bank\navigation_node_base;
use moodle_url;

/**
 * Class navigation.
 *
 * Plugin entrypoint for navigation.
 *
 * @package    qbank_bulkupdate
 * @copyright  2025 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation extends navigation_node_base {

    /**
     * Title for this node.
     */
    public function get_navigation_title(): string {
        return get_string('navandheader', 'qbank_bulkupdate');
    }

    /**
     * Key for this node.
     */
    public function get_navigation_key(): string {
        return 'bulkupdate';
    }

    /**
     * URL for this node
     */
    public function get_navigation_url(): moodle_url {
        return new moodle_url('/question/bank/bulkupdate/bulkupdate.php');
    }

    /**
     * Tab capabilities.
     *
     * If it has capabilities to be checked, it will return the array of capabilities.
     *
     * @return null|array
     */
    public function get_navigation_capabilities(): ?array {
        return [
            'moodle/question:editall',
            'moodle/question:editmine',
        ];
    }
}
