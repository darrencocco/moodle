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
 * Question behaviour for questions that can only be graded manually.
 *
 * @package    qbehaviour
 * @subpackage manualgraded
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../manualgraded/behaviour.php');

/**
 * Question behaviour for questions that can only be graded manually.
 *
 * The student enters their response during the attempt, and it is saved. Later,
 * when the whole attempt is finished, the attempt goes into the NEEDS_GRADING
 * state, and the teacher must grade it manually.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_manualgradedfrequentsaves extends qbehaviour_manualgraded {
    use autosaveconversion;
}
