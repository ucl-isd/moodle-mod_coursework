@mod @mod_coursework @javascript
Feature: Teachers/Moderators who can view all the feedback should be able to see it all together on one page
  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity             | coursework    |
      | course               | C1            |
      | name                 | CW1           |
      | numberofmarkers      | 2             |
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
      | student1    | CW1        | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | CW1        | teacher1 | assessor_1      | 88    | Teacher1 comment |
      | student1    | CW1        | teacher2 | assessor_2      | 77    | Teacher2 comment |

  Scenario: If mark is not agreed, the link is to the edit page
    Given I am on the "CW1" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "99"
    And I press "Save as draft"
    And I click on "99" "link" in the "student1" "table_row"
    Then I should not see "All feedback"
    And I am on the "CW1" "coursework activity" page logged in as "teacher1"
    And I click on "99" "link" in the "student1" "table_row"
    Then I should not see "All feedback"

  Scenario: If mark is agreed, the link is to the view all feedback page, unless we are a manager
    Given I am on the "CW1" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "99"
    And I press "Save and finalise"
    And I click on "99" "link" in the "student1" "table_row"
    Then I should not see "All feedback"
    And I am on the "CW1" "coursework activity" page logged in as "teacher1"
    And I click on "99" "link" in the "student1" "table_row"
    Then I should see "All feedback"
