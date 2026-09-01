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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Drag&drop matching question renderer class.
 *
 * @package   qtype_ddmatch
 * @copyright  2009 The Open University
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for drag&drop matching questions.
 */
class qtype_ddmatch_renderer extends qtype_with_combined_feedback_renderer {
    /**
     * Generate the HTML required for a ddmatch question.
     *
     * @param question_attempt $qa the question attempt.
     * @param question_display_options $options the options for display.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        // We use the question quite a lot so store a reference to it once.
        $question = $qa->get_question();

        // Put together the basic question text and answer block.
        $output  = '';
        $output .= $this->construct_questiontext($question->format_questiontext($qa));
        $output .= $this->construct_answerblock($qa, $question, $options);

        $this->page->requires->string_for_js('draganswerhere', 'qtype_ddmatch');
        $this->page->requires->js_call_amd('qtype_ddmatch/dragdrop', 'init', [
            $qa->get_outer_question_div_unique_id(),
            $options->readonly,
        ]);
        if ($qa->get_state() === question_state::$invalid) {
            $response = $qa->get_last_qt_data();
            $output .= html_writer::nonempty_tag(
                'div',
                $question->get_validation_error($response),
                ['class' => 'validationerror']
            );
        }

        return $output;
    }

    /**
     * Format the question choices for display.
     *
     * @param question_attempt $qa the question attempt.
     * @return array of formatted choices, indexed by choice key.
     */
    public function format_choices(question_attempt $qa) {
        $question = $qa->get_question();
        $choices = [];
        foreach ($question->get_choice_order() as $key => $choiceid) {
            $choices[$key] = $question->format_text(
                $question->choices[$choiceid],
                FORMAT_MOODLE,
                $qa,
                'qtype_ddmatch',
                'subanswer',
                $choiceid
            );
        }
        return $choices;
    }

    /**
     * Generate the specific feedback for the question.
     *
     * @param question_attempt $qa the question attempt.
     * @return string HTML fragment.
     */
    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    /**
     * Generate an automatic description of the correct response.
     *
     * @param question_attempt $qa the question attempt.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa) {
        if ($qa->get_state()->is_correct()) {
            // The answer was correct so we don't need to do anything further.
            return '';
        }

        $question = $qa->get_question();
        $stemorder = $question->get_stem_order();
        $choices = $this->format_choices($qa, true);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable correctanswertable';
        $table->size = ['50%', '50%'];
        foreach ($stemorder as $stemid) {
            $row = new html_table_row();
            $row->cells[] = $question->format_text(
                $question->stems[$stemid],
                $question->stemformat[$stemid],
                $qa,
                'qtype_ddmatch',
                'subquestion',
                $stemid
            );
            $row->cells[] = $choices[$question->get_right_choice_for($stemid)];

            $table->data[] = $row;
        }

        return get_string('correctansweris', 'qtype_match', html_writer::table($table));
    }

    /**
     * Construct the question text displayed to the user.
     *
     * @param string $questiontext the question text to render.
     * @return string the rendered question text.
     */
    public function construct_questiontext($questiontext) {
        return html_writer::tag('div', $questiontext, [
            'class' => 'qtext',
        ]);
    }

    /**
     * Construct the answer block area.
     *
     * @param question_attempt $qa the question attempt.
     * @param question_definition $question the question being displayed.
     * @param question_display_options $options the options for display.
     * @return string HTML fragment.
     */
    public function construct_answerblock($qa, $question, $options) {
        $stemorder = $question->get_stem_order();
        $response = $qa->get_last_qt_data();
        $choices = $this->format_choices($qa);
        $o  = html_writer::start_tag('div', ['class' => 'ablock']);
        $o .= html_writer::start_tag('div', ['class' => 'divanswer']);
        $o .= html_writer::start_tag('table', ['class' => 'answer']);
        $o .= html_writer::start_tag('tbody');
        $parity = 0;
        $curfieldname = null;
        foreach ($stemorder as $key => $stemid) {
            $o .= html_writer::start_tag('tr', ['class' => 'r' . $parity]);
            $o .= html_writer::tag(
                'td',
                $this->construct_stem_cell($qa, $question, $stemid),
                ['class' => 'text dragdrop-question']
            );
            $classes = ['control'];
            $feedbackimage = '';
            $curfieldname = $question->get_field_name($key);
            if (array_key_exists($curfieldname, $response)) {
                $selected = (int) $response[$curfieldname];
            } else {
                $selected = 0;
            }
            $fraction = (int) ($selected && $selected == $question->get_right_choice_for($stemid));

            if ($options->correctness && $selected) {
                $classes[]  = $this->feedback_class($fraction);
                $feedbackimage = $this->feedback_image($fraction);
            }
            $dragdropclasses = $classes;
            $classes[] = 'hiddenifjs';
            $dragdropclasses[] = 'visibleifjs';
            $o .= html_writer::tag(
                'td',
                $this->construct_choice_cell_select($qa, $options, $choices, $curfieldname, $selected) .
                    ' ' . $feedbackimage,
                ['class' => implode(' ', $classes)]
            );
            $o .= html_writer::tag(
                'td',
                $this->construct_choice_cell_dragdrop($qa, $choices, $stemid, $curfieldname, $selected) .
                ' ' . $feedbackimage,
                ['class' => implode(' ', $dragdropclasses)]
            );
            $o .= html_writer::end_tag('tr');
            $parity = 1 - $parity;
        }
        $o .= html_writer::end_tag('tbody');
        $o .= html_writer::end_tag('table');
        $o .= html_writer::end_tag('div');
        $o .= $this->construct_available_dragdrop_choices($qa, $question);
        $o .= html_writer::end_tag('div');
        $o .= html_writer::tag('div', '', ['class' => 'clearer']);
        return $o;
    }

    /**
     * Construct the HTML for the stem cell of one row of the answer table.
     *
     * @param question_attempt $qa the question attempt.
     * @param question_definition $question the question being displayed.
     * @param int $stemid the id of the stem to render.
     * @return string HTML fragment.
     */
    private function construct_stem_cell($qa, $question, $stemid) {
        return $question->format_text(
            $question->stems[$stemid],
            $question->stemformat[$stemid],
            $qa,
            'qtype_ddmatch',
            'subquestion',
            $stemid
        );
    }

    /**
     * Construct the HTML for the non-JS choice select for one row of the answer table.
     *
     * @param question_attempt $qa the question attempt.
     * @param question_display_options $options the options for display.
     * @param array $choices the formatted choices, indexed by choice key.
     * @param string $curfieldname the response field name for this row.
     * @param int $selected the currently selected choice key, or 0.
     * @return string HTML fragment.
     */
    private function construct_choice_cell_select($qa, $options, $choices, $curfieldname, $selected) {
        return html_writer::select(
            $choices,
            $qa->get_qt_field_name($curfieldname),
            $selected,
            ['0' => 'choose'],
            ['disabled' => $options->readonly]
        );
    }

    /**
     * Construct the HTML for the drag-and-drop target for one row of the answer table.
     *
     * @param question_attempt $qa the question attempt.
     * @param array $choices the formatted choices, indexed by choice key.
     * @param int $stemid the id of the stem for this row.
     * @param string $curfieldname the response field name for this row.
     * @param int $selected the currently selected choice key, or 0.
     * @return string HTML fragment.
     */
    private function construct_choice_cell_dragdrop($qa, $choices, $stemid, $curfieldname, $selected) {
        $placeholderclasses = ['placeholder'];
        $li = '';
        // Check whether an answer has already been selected.
        if ($selected !== 0) {
            // An answer has already been selected, display it as well.
            $attributes = [
                'data-id' => $selected,
                'class' => 'matchdrag copy'];
            $li = html_writer::tag('li', $choices[$selected], $attributes);

            // Add the hidden placeholder class so that the placeholder is initially hidden.
            $placeholderclasses[] = 'hidden';
        }
        $placeholder = html_writer::tag('li', html_writer::tag(
            'p',
            get_string('draganswerhere', 'qtype_ddmatch')
        ), [
        'class' => implode(' ', $placeholderclasses),
        ]);
        $li = $placeholder . $li;
        $question = $qa->get_question();
        $attributes = [
        'id'    => 'ultarget' . $question->id . '_' . $stemid,
        'name'  => $qa->get_qt_field_name($curfieldname),
        'class' => 'place' . $stemid . ' drop active',
        'data-selectname' => $qa->get_qt_field_name($curfieldname),
        ];
        $output = html_writer::tag('ul', $li, $attributes);
        return $output;
    }

    /**
     * Construct the list of available answers for use in the drag and drop
     * interface.
     *
     * @param question_attempt $qa the question attempt.
     * @param question_definition $question the question being displayed.
     * @return string HTML fragment.
     */
    public function construct_available_dragdrop_choices($qa, $question) {
        $choiceorder = $question->get_choice_order();
        $choices = $this->format_choices($qa, true);

        $uldata = '';
        foreach (array_keys($choiceorder) as $key) {
            $attributes = [
                'data-id' => $key,
                'class' => 'draghome infinite dragdrop-choice choice' . $key,
            ];
            $li = html_writer::tag('li', $choices[$key], $attributes);
            $uldata .= $li;
        }
        $attributes = [
        'id'    => 'ulorigin' . $question->id,
        'class' => 'draghomes visibleifjs'];
        $o = html_writer::tag('ul', $uldata, $attributes);
        $classes = ['answercontainer'];
        $o = html_writer::tag('div', $o, ['class' => implode(' ', $classes)]);
        return $o;
    }
}
