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
 * Drag-and-drop matching question type classe.
 *
 * @package    qtype_ddmatch
 *
 * @copyright  2007 Adriane Boyd (adrianeboyd@gmail.com)
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/match/question.php');

/**
 * Represents a drag&drop matching question.
 * Based on core matching question.
 */
class qtype_ddmatch_question extends qtype_match_question {
    /** @var array format of each choice, keyed by choice id. */
    public mixed $choiceformat = [];

    /**
     * Get a short textual summary of the question, for reports.
     *
     * @return string a short summary of the question.
     */
    public function get_question_summary() {
        $question = $this->html_to_text($this->questiontext, $this->questiontextformat);

        $stems = [];
        foreach ($this->stemorder as $stemid) {
            $stems[] = $this->html_to_text($this->stems[$stemid], $this->stemformat[$stemid]);
        }

        $choices = [];
        foreach ($this->choiceorder as $choiceid) {
            $choices[] = $this->choices[$choiceid];
        }

        return $question . ' {' . implode('; ', $stems) . '} -> {' .
                implode('; ', $choices) . '}';
    }

    /**
     * Summarise a response, for use in the response history.
     *
     * @param array $response a response.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        $matches = [];
        foreach ($this->stemorder as $key => $stemid) {
            if (array_key_exists($this->field($key), $response) && $response[$this->field($key)]) {
                $matches[] = $this->html_to_text(
                    $this->stems[$stemid],
                    $this->stemformat[$stemid]
                ) . ' -> ' .
                        $this->choices[$this->choiceorder[$response[$this->field($key)]]];
            }
        }

        if (empty($matches)) {
            return null;
        }

        return implode('; ', $matches);
    }

    /**
     * Whether the current user can access a particular file area.
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_display_options $options the options that control display of the question.
     * @param string $component the name of the component we are serving files for.
     * @param string $filearea the name of the file area.
     * @param array $args the remaining bits of the file path.
     * @param bool $forcedownload whether the user must be forced to download the file.
     * @return bool true if the user can access this file.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'qtype_ddmatch' && $filearea == 'subquestion') {
            $subqid = reset($args);
            return array_key_exists($subqid, $this->stems);
        } else if ($component == 'qtype_ddmatch' && $filearea == 'subanswer') {
            $subqid = reset($args);
            return array_key_exists($subqid, $this->choices);
        } else if (
            $component == 'question' && in_array(
                $filearea,
                ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback']
            )
        ) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea);
        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access(
                $qa,
                $options,
                $component,
                $filearea,
                $args,
                $forcedownload
            );
        }
    }

    /**
     * Get the name of the form field used for a given stem.
     *
     * @param int $key stem number.
     * @return string the corresponding form field name.
     */
    public function get_field_name($key) {
        return $this->field($key);
    }
}
