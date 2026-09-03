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
 * Restore code for the drag and drop matching question type.
 *
 * @package    qtype_ddmatch
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @author DualCube <admin@dualcube.com>
 * @copyright  2017 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class that provides the necessary information
 * needed to restore one ddmatch qtype plugin.
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_ddmatch_plugin extends restore_qtype_plugin {
    /**
     * A simple answer, questiontext to id cache for a ddmatch answers.
     * @var array
     */
    private $questionsubcache = [];

    /**
     * The id of the current question in the questionsubcache.
     * @var int
     */
    private $questionsubcacheid = null;


    /**
     * Returns the paths to be handled by the plugin at question level.
     */
    protected function define_question_plugin_structure() {

        $paths = [];

        // Add own qtype stuff.
        $elename = 'matchoptions';
        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/matchoptions');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'match';
        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/matches/match');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Convert the backup structure of this question type into a structure matching its question data
     *
     * This should take the hierarchical array of tags from the question's backup structure, and return a structure that matches
     * that returned when calling {@see get_question_options()} for this question type.
     * See https://docs.moodle.org/dev/Question_data_structures#Representation_1:_%24questiondata for an explanation of this
     * structure.
     *
     * This data will then be used to produce an identity hash for comparison with questions in the database.
     *
     * This base implementation deals with all common backup elements created by the add_question_*_options() methods in this class,
     * plus elements added by ::define_question_plugin_structure() named for the qtype. The question type will need to extend
     * this function if ::define_question_plugin_structure() adds any other elements to the backup.
     *
     * @param array $backupdata The hierarchical array of tags from the backup.
     * @return \stdClass The questiondata object.
     */
    public static function convert_backup_to_questiondata(array $backupdata): \stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);

        $questiondata->options = (object) $backupdata["plugin_qtype_ddmatch_question"]['matchoptions'][0];
        $questiondata->options->subquestions = array_map(
            fn($match) => (object) $match,
            $backupdata["plugin_qtype_ddmatch_question"]['matches']['match'] ?? [],
        );

        return $questiondata;
    }

    /**
     * Return a list of paths to fields to be removed from questiondata before creating an identity hash.
     *
     * Fields that should be excluded from common elements such as answers or numerical units that are used by the plugin will
     * be excluded automatically. This method just needs to define any specific to this plugin, such as foreign keys used in the
     * plugin's tables.
     *
     * The returned array should be a list of slash-delimited paths to locate the fields to be removed from the questiondata object.
     * For example, if you want to remove the field `$questiondata->options->questionid`, the path would be '/options/questionid'.
     * If a field in the path is an array, the rest of the path will be applied to each object in the array. So if you have
     * `$questiondata->options->answers[]`, the path '/options/answers/id' will remove the 'id' field from each element of the
     * 'answers' array.
     *
     * @return array
     */
    protected function define_excluded_identity_hash_fields(): array {
        return parent::define_excluded_identity_hash_fields() + [
            '/options/subquestions/id',
            '/options/subquestions/questionid',
        ];
    }

    /**
     * Process the qtype/matchoptions element.
     *
     * @param array $data the parsed backup data.
     */
    public function process_matchoptions($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its qtype_ddmatch_options too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->questionid = $newquestionid;

            // It is possible for old backup files to contain unique key violations.
            // We need to check to avoid that.
            if (!$DB->record_exists('qtype_ddmatch_options', ['questionid' => $data->questionid])) {
                $newitemid = $DB->insert_record('qtype_ddmatch_options', $data);
                $this->set_mapping('qtype_ddmatch_options', $oldid, $newitemid);
            }
        }
    }

    /**
     * Process the qtype/matches/match element.
     *
     * @param array $data the parsed backup data.
     */
    public function process_match($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        if ($questioncreated) {
            // If the question has been created by restore, we need to create its
            // qtype_ddmatch_subquestions too.

            // Adjust some columns.
            $data->questionid = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_ddmatch_subquestions', $data);
            // Create mapping (there are files and states based on this).
            $this->set_mapping('qtype_ddmatch_subquestions', $oldid, $newitemid);
            if (isset($data->code)) {
                $this->set_mapping('qtype_ddmatch_subquestion_codes', $data->code, $newitemid);
            }
        } else {
            // The ddmatch questions require mapping of qtype_ddmatch_subquestions, because
            // they are used by question_states->answer.

            // Have we cached the current question?
            if ($this->questionsubcacheid !== $newquestionid) {
                // The question changed, purge and start again!
                $this->questionsubcache = [];

                $potentialsubs = $DB->get_records(
                    'qtype_ddmatch_subquestions',
                    ['questionid' => $newquestionid],
                    '',
                    'id, questiontext, answertext'
                );

                $this->questionsubcacheid = $newquestionid;
                // Cache all cleaned answers and questiontext.
                foreach ($potentialsubs as $potentialsub) {
                    // Clean in the same way than xml_writer::xml_safe_utf8().
                    $cleanquestion = preg_replace(
                        '/[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f]/i',
                        '',
                        $potentialsub->questiontext
                    ); // Clean CTRL chars.
                    $cleanquestion = preg_replace("/\r\n|\r/", "\n", $cleanquestion); // Normalize line ending.

                    $cleananswer = preg_replace(
                        '/[\x00-\x08\x0b-\x0c\x0e-\x1f\x7f]/i',
                        '',
                        $potentialsub->answertext
                    ); // Clean CTRL chars.
                    $cleananswer = preg_replace("/\r\n|\r/", "\n", $cleananswer); // Normalize line ending.

                    $this->questionsubcache[$cleanquestion][$cleananswer] = $potentialsub->id;
                }
            }

            if (!isset($this->questionsubcache[$data->questiontext][$data->answertext])) {
                throw new restore_step_exception('error_qtype_ddmatch_subquestion_missing_in_db', $data);
            }
            $newitemid = $this->questionsubcache[$data->questiontext][$data->answertext];
        }

        // Found one. Let's create the mapping.
        $this->set_mapping('qtype_ddmatch_subquestions', $oldid, $newitemid);
    }

    /**
     * Recode the components of a response that refer to subquestion ids.
     *
     * @param int $questionid the question being processed.
     * @param int $sequencenumber the sequence number of the response.
     * @param array $response the response data.
     * @return array the recoded response data.
     *
     * $questionid and $sequencenumber are unused here, but this overrides
     * restore_qtype_plugin::recode_response(), which is called with this
     * exact signature throughout the restore process - dropping either
     * parameter would break the override.
     */
    public function recode_response($questionid, $sequencenumber, array $response) {
        if (array_key_exists('_stemorder', $response)) {
            $response['_stemorder'] = $this->recode_ddmatch_sub_order($response['_stemorder']);
        }
        if (array_key_exists('_choiceorder', $response)) {
            $response['_choiceorder'] = $this->recode_ddmatch_sub_order($response['_choiceorder']);
        }
        return $response;
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for ddmatch questions.
     *
     * answer is one comma separated list of hypen separated pairs
     * containing question_ddmatch_sub->id and question_ddmatch_sub->code, which
     * has been remapped to be qtype_ddmatch_subquestions->id, since code no longer exists.
     *
     * @param object $state the question_states record.
     * @return string the recoded answer.
     */
    public function recode_legacy_state_answer($state) {
        $answer = $state->answer;
        $resultarr = [];
        foreach (explode(',', $answer) as $pair) {
            $pairarr = explode('-', $pair);
            $id = $pairarr[0];
            $code = $pairarr[1];
            $newid = $this->get_mappingid('qtype_ddmatch_subquestions', $id);
            if ($code) {
                $newcode = $this->get_mappingid('qtype_ddmatch_subquestion_codes', $code);
            } else {
                $newcode = $code;
            }
            $resultarr[] = $newid . '-' . $newcode;
        }
        return implode(',', $resultarr);
    }

    /**
     * Recode the choice order as stored in the response.
     * @param string $order the original order.
     * @return string the recoded order.
     */
    protected function recode_ddmatch_sub_order($order) {
        $neworder = [];
        foreach (explode(',', $order) as $id) {
            if ($newid = $this->get_mappingid('qtype_ddmatch_subquestions', $id)) {
                $neworder[] = $newid;
            }
        }
        return implode(',', $neworder);
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder.
     */
    public static function define_decode_contents() {

        $contents = [];

        $contents[] = new restore_decode_content(
            'qtype_ddmatch_subquestions',
            ['questiontext', 'answertext'],
            'qtype_ddmatch_subquestions'
        );

        $fields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        $contents[] = new restore_decode_content('qtype_ddmatch_options', $fields, 'qtype_ddmatch_options');

        return $contents;
    }
}
