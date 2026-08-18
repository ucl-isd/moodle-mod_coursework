@mod @mod_coursework @mod_coursework_grading_audit @javascript
Feature: As a moderator I should be able to see the Grading Audit report with statistics about marks given.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework   |
      | course          | C1           |
      | name            | Coursework-A |
      | numberofmarkers | 1            |
      | moderationagreementenabled | 1 |
    And the following "users" exist:
      | username       | firstname | lastname   | email                 |
      | teacher1       | Teacher   | One        | teacher1@example.com  |
      | moderator1     | Moderator | One        | moderator1@example.com|
      | student1       | Student   | One        | student1@example.com  |
      | student2       | Student   | Two        | student2@example.com  |
      | student3       | Student   | Three      | student3@example.com  |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | student1   | C1     | student        |
      | student2   | C1     | student        |
      | student3   | C1     | student        |
      | teacher1   | C1     | editingteacher |
      | moderator1 | C1     | manager        |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework   | finalisedstatus |
      | student1    | Coursework-A | 1               |
      | student2    | Coursework-A | 1               |
      | student3    | Coursework-A | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework   | assessor | stageidentifier | grade | feedbackcomment |
      | student1    | Coursework-A | teacher1 | assessor_1      | 58    | Quite good      |
      | student2    | Coursework-A | teacher1 | assessor_1      | 1     | Awful           |
      | student3    | Coursework-A | teacher1 | assessor_1      | 47    | Average         |
    And the following "permission overrides" exist:
      | capability                    | permission | role    | contextlevel | reference |
      | mod/coursework:moderate       | Allow      | manager | Course       | C1        |

  Scenario: A moderator can see the Grading Audit page
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    Then I should see "Grading Audit"

  Scenario: A non-moderator cannot see the Grading Audit page
    Given I am on the "Coursework-A" "coursework activity" page logged in as "teacher1"
    Then I should not see "Grading Audit"

  Scenario: A moderator can see the grading statistics for the activity
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    When I follow "Grading Audit"
    Then I should see "Course 1" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Course']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Coursework-A" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Coursework title']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Teacher One" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Markers']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Moderator One" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Moderators']/preceding-sibling::th)+1]" "xpath_element"
    # The rest of the data is covered under the unit tests, that it should be calculated correctly. So we'll stop here.
