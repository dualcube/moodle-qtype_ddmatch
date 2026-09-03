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
 * Serve question type files
 *
 * @package qtype_ddmatch
 *
 * @author Dongsheng Cai <dongsheng@moodle.com>
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve a file from the drag-and-drop matching question type.
 *
 * @param stdClass $course course object.
 * @param stdClass $cm course module object.
 * @param context $context context object.
 * @param string $filearea file area.
 * @param array $args extra arguments.
 * @param bool $forcedownload whether or not force download.
 * @param array $options additional options affecting the file serving.
 * @return bool false if file not found, does not return if found - just sends the file.
 *
 * $cm is unused - question contexts aren't course-module-scoped - but this
 * is Moodle's fixed {component}_pluginfile() callback signature, called
 * positionally by core's file-serving code with all these arguments.
 */
function qtype_ddmatch_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_ddmatch', $filearea, $args, $forcedownload, $options);
}
