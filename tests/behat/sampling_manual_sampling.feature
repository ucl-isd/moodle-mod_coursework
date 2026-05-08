@mod @mod_coursework @mod_coursework_sampling
Feature: Manual sampling

  As a teacher
  I can manually select the submissions to be included in the sample for a single feedback stage
  So I can select correct sample of students for double marking

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity          | coursework  |
      | course            | C1          |
      | name              | Coursework  |
      | allocationenabled | 0           |
      | samplingenabled   | 1           |
      | numberofmarkers   | 2           |
      | deadline          | ##-1 week## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher2 | assessor_1      | 67    | New comment here |

  @javascript
  Scenario: Manual sampling should not include student when not selected
    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student1" "table_row" to these values:
      | Included in sample | 0 |
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Add mark" in the "student student1" "table_row"

    When I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student1" "table_row" to these values:
      | Included in sample | 1 |
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Add mark" in the "student student1" "table_row"
