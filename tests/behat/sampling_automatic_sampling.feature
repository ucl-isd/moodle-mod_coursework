@mod @mod_coursework @mod_coursework_sampling_automatic
Feature: Automatic sampling using total number of students in stage 1 and 2

  As a course administrator setting up a coursework instance for a large group of students
  I want to be able to specify a set of rules that will automatically create a total sample for second markers
  So that this process does not need to be done manually, wasting lots of time.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | samplingenabled | 1          |
      | numberofmarkers | 3          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following config values are set as admin:
      | config                 | value | plugin         |
      | eliminaterandmosiation | 1     | mod_coursework |

  Scenario: Automatically allocating a total for stage 3 based on stage 2
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy     | Automatic |
      | assessor_2_sampletotal_checkbox | 1         |
      | assessor_2_sampletotal          | 100       |
      | assessor_3_samplingstrategy     | Automatic |
      | assessor_3_sampletotal_checkbox | 1         |
      | assessor_3_sampletotal          | 50        |
    And I press "save_sampling"
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student1" row "Marker 3" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 3" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 3" column of "mod_coursework_allocatemarkers" table should contain "Included in sample"
