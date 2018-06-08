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
 * Test for replace content webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_ally\webservice\replace_content;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/abstract_testcase.php');

/**
 * Test for replace content webservice.
 *
 * @package   tool_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_ally_webservice_replace_content_testcase extends tool_ally_abstract_testcase {

    public function setUp() {
        $this->resetAfterTest();
        $roleid = $this->assignUserCapability('moodle/course:view', context_system::instance()->id);
        $this->assignUserCapability('moodle/course:viewhiddencourses', context_system::instance()->id, $roleid);
    }

    /**
     * Test the web service when used to replace a single content item.
     */
    public function test_service_course_summary() {
        global $DB;

        $coursesummary = '<p>My course summary</p>';
        $course = $this->getDataGenerator()->create_course(['summary' => $coursesummary]);
        $coursesummaryreplaced = $coursesummary.'<p>REPLACED!</p>';
        $result = replace_content::service(
                $course->id, 'course', 'course', 'summary', $coursesummaryreplaced);
        $this->assertTrue($result['success']);
        $course = $DB->get_record('course', ['id' => $course->id]);
        $this->assertEquals($coursesummaryreplaced, $course->summary);
    }

    public function test_service_course_section() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $section0summary = '<p>First section summary</p>';
        $section = $this->getDataGenerator()->create_course_section(
            ['section' => 0, 'course' => $course->id]);
        $DB->update_record('course_sections', (object) [
            'id' => $section->id,
            'summary' => $section0summary
        ]);
        $section0summaryreplaced = $section0summary.'</p>REPLACED!</p>';
        $result = replace_content::service(
            $section->id, 'course', 'course_sections', 'summary', $section0summaryreplaced);
        $this->assertTrue($result['success']);
        $section = $DB->get_record('course_sections', ['id' => $section->id]);
        $this->assertEquals($section0summaryreplaced, $section->summary);
    }

    /**
     * @param string $modname
     * @param string $table
     * @param string $field
     * @return mixed|stdClass
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    private function module_replace_test($modname, $table, $field = 'intro') {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $modintro = '<p>My original intro content</p>';
        $mod = $this->getDataGenerator()->create_module($modname,
            ['course' => $course->id, $field => $modintro]);
        $generatedmod = $mod;
        $modintroreplaced = $modintro.'</p>REPLACED</p>';
        $result = replace_content::service(
            $mod->id, $modname, $table, $field, $modintroreplaced
        );
        $this->assertTrue($result['success']);
        $mod = $DB->get_record($table, ['id' => $mod->id]);
        $this->assertEquals($modintroreplaced, $mod->intro);
        return $generatedmod; // Sometimes the generated mod has more data than the db row - e.g. cmid.
    }

    public function test_service_label() {
        $this->module_replace_test('label', 'label');
    }

    public function test_service_assign() {
        $this->module_replace_test('assign', 'assign');
    }

    public function test_service_glossary() {
        global $USER, $DB;


        $glossary = $this->module_replace_test('glossary', 'glossary');
        $courseid = $glossary->course;

        $this->setAdminUser();
        $record = new stdClass();
        $record->course = $courseid;
        $record->glossary = $glossary->id;
        $record->userid = $USER->id;
        $entry = self::getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, $record);
        $definitionreplaced = '<p>Content replaced!</p>';
        $result = replace_content::service(
            $entry->id, 'glossary', 'glossary_entries', 'definition', $definitionreplaced
        );
        $this->assertTrue($result['success']);

        $entry = $DB->get_record('glossary_entries', ['id' => $entry->id]);
        $this->assertEquals($definitionreplaced, $entry->definition);
    }

    public function test_service_forum() {
        global $USER, $DB;

        $forum = $this->module_replace_test('forum', 'forum');
        $courseid = $forum->course;

        $this->setAdminUser();
        $record = new stdClass();
        $record->course = $courseid;
        $record->forum = $forum->id;
        $record->userid = $USER->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        $posttitle = 'My post title';
        $postmessage = '<p>My post message</p>';
        $record = new stdClass();
        $record->discussion = $discussion->id;
        $record->userid = $USER->id;
        $record->subject = $posttitle;
        $record->message = $postmessage;
        $record->messageformat = FORMAT_HTML;
        $post = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);

        // Test post replace.
        $postmessagereplaced = $postmessage.'<span>Replaced</span>';
        $result = replace_content::service(
            $post->id, 'forum', 'forum_posts', 'message', $postmessagereplaced
        );
        $this->assertTrue($result['success']);

        $post = $DB->get_record('forum_posts', ['id' => $post->id]);
        $this->assertEquals($postmessagereplaced, $post->message);
    }
}
