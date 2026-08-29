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
 * Moodle 1.9 backup conversion code for the drag and drop matching question type.
 *
 * @package    qtype_ddmatch
 *
 * @author DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Matching question type conversion handler.
 */
class moodle1_qtype_ddmatch_handler extends moodle1_qtype_handler {
    /**
     * Get the paths within the question that this handler needs to process.
     *
     * @return array
     */
    public function get_question_subpaths() {
        return [
            'DDMATCHS/MATCH',
        ];
    }

    /**
     * Appends the ddmatch specific information to the question.
     */
    public function process_question(array $data, array $raw) {
        global $CFG;

        // Populate the list of matches first to get their ids.
        // Note that the field is re-populated on restore anyway but let us
        // do our best to produce valid backup files.
        $matchids = [];
        if (isset($data['ddmatchs']['match'])) {
            foreach ($data['ddmatchs']['match'] as $match) {
                $matchids[] = $match['id'];
            }
        }

        // Convert match options.
        $matchoptions = [];
        $matchoptions['id'] = $this->converter->get_nextid();
        $matchoptions['subquestions'] = implode(',', $matchids);
        $matchoptions['shuffleanswers'] = $data['shuffleanswers'];
        $this->write_xml('matchoptions', $matchoptions, ['/matchoptions/id']);

        // Convert ddmatches.
        $this->xmlwriter->begin_tag('matches');
        if (isset($data['ddmatchs']['match'])) {
            foreach ($data['ddmatchs']['match'] as $match) {
                // Replay the upgrade step 2009072100.
                $match['questiontextformat'] = 0;
                if ($CFG->texteditors !== 'textarea' && $data['oldquestiontextformat'] == FORMAT_MOODLE) {
                    $match['questiontext'] = text_to_html($match['questiontext'], false, false, true);
                    $match['questiontextformat'] = FORMAT_HTML;
                } else {
                    $match['questiontextformat'] = $data['oldquestiontextformat'];
                }
                if ($CFG->texteditors !== 'textarea' && $data['oldquestiontextformat'] == FORMAT_MOODLE) {
                    $match['answertext'] = text_to_html($match['answertext'], false, false, true);
                    $match['answertextformat'] = FORMAT_HTML;
                } else {
                    $match['answertextformat'] = $data['oldquestiontextformat'];
                }
                $match['questiontext'] = $this->migrate_files(
                    $match['questiontext'],
                    'qtype_ddmatch',
                    'subquestion',
                    $match['id']
                );
                $match['answertext'] = $this->migrate_files(
                    $match['answertext'],
                    'qtype_ddmatch',
                    'subanswer',
                    $match['id']
                );
                $this->write_xml('match', $match, ['/match/id']);
            }
        }
        $this->xmlwriter->end_tag('matches');
    }
}
