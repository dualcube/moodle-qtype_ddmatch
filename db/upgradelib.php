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
 * Upgrade library code for the match question type.
 *
 * @package    qtype_ddmatch
 *
 * @copyright  2010 The Open University
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class for converting attempt data for match questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddmatch_qe2_attempt_updater extends question_qtype_attempt_updater {
    /** @var array stems, indexed by stem id. */
    protected $stems;
    /** @var array choices, indexed by choice id. */
    protected $choices;
    /** @var array right choice id, indexed by stem id. */
    protected $right;
    /** @var array order in which the stems should be displayed. */
    protected $stemorder;
    /** @var array order in which the choices should be displayed. */
    protected $choiceorder;
    /** @var array choice display position, indexed by choice id. */
    protected $flippedchoiceorder;

    /**
     * Get a textual summary of the question.
     *
     * @return string summary of the question.
     */
    public function question_summary() {
        $this->stems = [];
        $this->choices = [];
        $this->right = [];

        foreach ($this->question->options->subquestions as $matchsub) {
            $key = array_search($matchsub->answertext, $this->choices);
            if ($key === false) {
                $key = $matchsub->id;
                $this->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $this->stems[$matchsub->id] = $this->to_text($matchsub->questiontext);
                $this->right[$matchsub->id] = $key;
            }
        }

        return $this->to_text($this->question->questiontext) . ' {' .
                implode('; ', $this->stems) . '} -> {' . implode('; ', $this->choices) . '}';
    }

    /**
     * Get a summary of the right answer.
     *
     * @return string summary of the right answer.
     */
    public function right_answer() {
        $answer = [];
        foreach ($this->stems as $key => $stem) {
            $answer[$stem] = $this->choices[$this->right[$key]];
        }
        return $this->make_summary($answer);
    }

    /**
     * Convert an old-style answer string into an array of stem => choice pairs.
     *
     * @param string $answer the answer, in the old format.
     * @return array stem id => choice code, for stems that were answered.
     */
    protected function explode_answer($answer) {
        if (!$answer) {
            return [];
        }
        $bits = explode(',', $answer);
        $selections = [];
        foreach ($bits as $bit) {
            [$stem, $choice] = explode('-', $bit);
            $selections[$stem] = $choice;
        }
        return $selections;
    }

    /**
     * Convert an array of stem => answer pairs into a textual summary.
     *
     * @param array $pairs stem text => answer text.
     * @return string a textual summary.
     */
    protected function make_summary($pairs) {
        $bits = [];
        foreach ($pairs as $stem => $answer) {
            $bits[] = $stem . ' -> ' . $answer;
        }
        return implode('; ', $bits);
    }

    /**
     * Look up the choice id for a given old-style choice code.
     *
     * @param string $choice the old-style choice code.
     * @return int|null the choice id, or null if not found.
     */
    protected function lookup_choice($choice) {
        foreach ($this->question->options->subquestions as $matchsub) {
            if ($matchsub->code == $choice) {
                if (array_key_exists($matchsub->id, $this->choices)) {
                    return $matchsub->id;
                } else {
                    return array_search($matchsub->answertext, $this->choices);
                }
            }
        }
        return null;
    }

    /**
     * Get a summary of the response given in a particular state.
     *
     * @param object $state the state whose response is to be summarised.
     * @return string|null a summary of the response, or null if there was none.
     */
    public function response_summary($state) {
        $choices = $this->explode_answer($state->answer);
        if (empty($choices)) {
            return null;
        }

        $pairs = [];
        foreach ($choices as $stemid => $choicekey) {
            if (array_key_exists($stemid, $this->stems) && $choices[$stemid]) {
                $choiceid = $this->lookup_choice($choicekey);
                if ($choiceid) {
                    $pairs[$this->stems[$stemid]] = $this->choices[$choiceid];
                } else {
                    $this->logger->log_assumption("Dealing with a place where the
                            student selected a choice that was later deleted for
                            match question {$this->question->id}");
                    $pairs[$this->stems[$stemid]] = '[CHOICE THAT WAS LATER DELETED]';
                }
            }
        }

        if ($pairs) {
            return $this->make_summary($pairs);
        } else {
            return '';
        }
    }

    /**
     * Determine whether a given state represents an answered response.
     *
     * @param object $state the state to check.
     * @return bool whether the state has a response.
     */
    public function was_answered($state) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $choice) {
            if ($choice) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set the data elements for the first step, representing the shuffled order.
     *
     * @param object $state the first state.
     * @param array $data the data to be updated.
     */
    public function set_first_step_data_elements($state, &$data) {
        $choices = $this->explode_answer($state->answer);
        foreach (array_keys($choices) as $key) {
            if (array_key_exists($key, $this->stems)) {
                $this->stemorder[] = $key;
            }
        }

        $this->choiceorder = array_keys($this->choices);
        shuffle($this->choiceorder);
        $this->flippedchoiceorder = array_combine(
            array_values($this->choiceorder),
            array_keys($this->choiceorder)
        );

        $data['_stemorder'] = implode(',', $this->stemorder);
        $data['_choiceorder'] = implode(',', $this->choiceorder);
    }

    /**
     * Supply first step data when it was not saved in the old attempt data.
     *
     * @param array $data the data to be updated.
     */
    public function supply_missing_first_step_data(&$data) {
        throw new coding_exception('qtype_ddmatch_updater::supply_missing_first_step_data ' .
                'not tested');
        $data['_stemorder'] = array_keys($this->stems);
        $data['_choiceorder'] = shuffle(array_keys($this->choices));
    }

    /**
     * Set the data elements for a step, representing the response given.
     *
     * @param object $state the state to process.
     * @param array $data the data to be updated.
     */
    public function set_data_elements_for_step($state, &$data) {
        $choices = $this->explode_answer($state->answer);

        foreach ($this->stemorder as $i => $key) {
            if (empty($choices[$key])) {
                $data['sub' . $i] = 0;
                continue;
            }
            $choice = $this->lookup_choice($choices[$key]);

            if (array_key_exists($choice, $this->flippedchoiceorder)) {
                $data['sub' . $i] = $this->flippedchoiceorder[$choice] + 1;
            } else {
                $data['sub' . $i] = 0;
            }
        }
    }
}
