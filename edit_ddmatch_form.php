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
 * Defines the editing form for the drag&drop match question type.
 *
 * @package    qtype_ddmatch
 * @copyright  2007 Adriane Boyd (adrianeboyd@gmail.com)
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Drag&drop match question type editing form definition.
 */
class qtype_ddmatch_edit_form extends question_edit_form {
    /**
     * Add the fields for one matching pair (a question and an answer editor).
     *
     * @param object $mform the form being built.
     * @param string $label the label to use for the question editor.
     * @param array $gradeoptions list of grade options.
     * @param array $repeatedoptions array of options to update in the form.
     * @param mixed $answersoption reference to return the name of the answers option.
     * @return array the form fields to repeat for each matching pair.
     *
     * $gradeoptions is unused here (this question type has no per-answer grade
     * field), but this overrides question_edit_form::get_per_answer_fields(),
     * which every caller (add_per_answer_fields()) invokes with this exact
     * signature - dropping the parameter would break the override.
     */
    protected function get_per_answer_fields(
        $mform,
        $label,
        $gradeoptions,
        &$repeatedoptions,
        &$answersoption
    ) {
        $mform->addElement(
            'static',
            'answersinstruct',
            get_string('availablechoices', 'qtype_match'),
            get_string('filloutthreeqsandtwoas', 'qtype_match')
        );

        $repeated = [];
        $repeated[] = $mform->createElement(
            'editor',
            'subquestions',
            $label,
            ['rows' => 3],
            $this->editoroptions
        );
        $repeated[] = $mform->createElement(
            'editor',
            'subanswers',
            get_string('answer'),
            ['rows' => 3],
            $this->editoroptions
        );
        $repeatedoptions['subquestions']['type'] = PARAM_RAW;
        $repeatedoptions['subanswers']['type'] = PARAM_RAW;
        $answersoption = 'subquestions';
        return $repeated;
    }

    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        $mform->addElement(
            'advcheckbox',
            'shuffleanswers',
            get_string('shuffle', 'qtype_match'),
            null,
            null,
            [0, 1]
        );
        $mform->addHelpButton('shuffleanswers', 'shuffle', 'qtype_match');
        $mform->setDefault('shuffleanswers', 1);

        $this->add_per_answer_fields($mform, get_string('questionno', 'question', '{no}'), 0);

        $this->add_combined_feedback_fields(true);
        $this->add_interactive_settings(true, true);
    }

    /**
     * Language string to use for 'Add {no} more {whatever we call answers}'.
     */
    protected function get_more_choices_string() {
        return get_string('blanksforxmorequestions', 'qtype_match');
    }

    /**
     * Perform the necessary preprocessing for the question editing form.
     *
     * @param object $question the data being passed to the form.
     * @return object $question the modified data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_combined_feedback($question, true);
        $question = $this->data_preprocessing_hints($question, true, true);

        if (empty($question->options)) {
            return $question;
        }

        $question->shuffleanswers = $question->options->shuffleanswers;

        $key = 0;
        foreach ($question->options->subquestions as $subquestion) {
            $question->subanswers[$key] = $subquestion->answertext;

            $draftid = file_get_submitted_draft_itemid('subquestions[' . $key . ']');
            $question->subquestions[$key] = [];
            $question->subquestions[$key]['text'] = file_prepare_draft_area(
                $draftid, // Draftid.
                $this->context->id, // Context.
                'qtype_ddmatch', // Component.
                'subquestion', // Filarea.
                !empty($subquestion->id) ? (int) $subquestion->id : null, // Item id.
                $this->fileoptions, // Options.
                $subquestion->questiontext // Text.
            );
            $question->subquestions[$key]['format'] = $subquestion->questiontextformat;
            $question->subquestions[$key]['itemid'] = $draftid;

            $draftid = file_get_submitted_draft_itemid('subanswers[' . $key . ']');
            $question->subanswers[$key] = [];
            $question->subanswers[$key]['text'] = file_prepare_draft_area(
                $draftid, // Draftid.
                $this->context->id, // Context.
                'qtype_ddmatch', // Component.
                'subanswer', // Filarea.
                !empty($subquestion->id) ? (int) $subquestion->id : null, // Item id.
                $this->fileoptions, // Options.
                $subquestion->answertext // Text.
            );
            $question->subanswers[$key]['format'] = $subquestion->answertextformat;
            $question->subanswers[$key]['itemid'] = $draftid;

            $key++;
        }

        return $question;
    }

    /**
     * Validate the question editing form.
     *
     * @param array $data the submitted form data.
     * @param array $files the submitted files.
     * @return array the errors, indexed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $answers = $data['subanswers'];
        $questions = $data['subquestions'];
        $questioncount = 0;
        $answercount = 0;
        foreach ($questions as $key => $question) {
            $trimmedquestion = trim($question['text']);
            $trimmedanswer = trim($answers[$key]['text']);
            if ($trimmedquestion != '') {
                $questioncount++;
            }
            if ($trimmedanswer != '' || $trimmedquestion != '') {
                $answercount++;
            }
            if ($trimmedquestion != '' && $trimmedanswer == '') {
                $errors['subanswers[' . $key . ']'] =
                        get_string('nomatchinganswerforq', 'qtype_match', $trimmedquestion);
            }
        }
        $numberqanda = new stdClass();
        $numberqanda->q = 1;
        $numberqanda->a = 2;
        if ($questioncount < 1) {
            $errors['subquestions[0]'] =
                    get_string('notenoughqsandas', 'qtype_match', $numberqanda);
        }
        if ($answercount < 2) {
            $errors['subanswers[1]'] =
                    get_string('notenoughqsandas', 'qtype_match', $numberqanda);
        }
        return $errors;
    }

    /**
     * Returns the question type name.
     *
     * @return string the question type name.
     */
    public function qtype() {
        return 'ddmatch';
    }
}
