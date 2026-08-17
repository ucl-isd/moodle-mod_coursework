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
      | numberofmarkers      | 1             |
    And the following "activity" exists:
      | activity             | coursework    |
      | course               | C1            |
      | name                 | CW2           |
      | samplingenabled      | 1             |
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
      | student1    | CW2        | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | CW1        | teacher1 | assessor_1      | 88    | Teacher1 comment |
      | student1    | CW2        | teacher1 | assessor_1      | 67    | Teacher1 comment |

  Scenario: Activity with 1 marker doesn't have the "View all feedback" link
    Given I am on the "CW1" "coursework activity" page logged in as "teacher1"
    Then I should not see "View all feedback"

  Scenario: If marker cannot see some of the feedback, the link is not visible
    Given I am on the "CW2" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student1" "table_row" to these values:
      | Included in sample | 1 |
    And I am on the "CW2" "coursework activity" page logged in as "teacher2"
    Then I should not see "View all feedback"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I set the field "Mark" to "12"
    And I press "Save and finalise"
    Then I should not see "View all feedback"

  Scenario: One marker can see all the feedback, and the agreed mark is complete, the link is visible
    Given I am on the "CW2" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "99"
    And I press "Save and finalise"
    And I am on the "CW2" "coursework activity" page logged in as "teacher2"
    Then I should see "View all feedback"
