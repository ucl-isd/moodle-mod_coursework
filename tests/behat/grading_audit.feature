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
      | teacher2       | Teacher   | Two        | teacher2@example.com  |
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
      | teacher2   | C1     | teacher        |
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
      | capability                    | permission | role           | contextlevel | reference |
      | mod/coursework:moderate       | Allow      | manager        | Course       | C1        |
      | mod/coursework:moderate       | Allow      | editingteacher | Course       | C1        |

  Scenario: A moderator can see the Grading Audit page
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    Then I should see "Grading Audit"

  Scenario: A non-moderator cannot see the Grading Audit page
    Given I am on the "Coursework-A" "coursework activity" page logged in as "teacher2"
    Then I should not see "Grading Audit"

  Scenario: A moderator can see the grading statistics for the activity
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    When I follow "Grading Audit"
    Then I should see "Course 1" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Course']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Coursework-A" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Coursework title']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Teacher One" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Markers']/preceding-sibling::th)+1]" "xpath_element"
    And I should see "Moderator One" in the "//table[@id='grading-audit-info']//tbody/tr[1]/td[count(//table[@id='grading-audit-info']//th[normalize-space()='Moderators']/preceding-sibling::th)+1]" "xpath_element"
    # The rest of the data is covered under the unit tests, that it should be calculated correctly. So we'll stop here.

  Scenario: A moderator can submit the grading audit appraisal form and if they are a manager they can still edit after finalisation
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    When I follow "Grading Audit"
    And I set the field "representative" to "Yes"
    And I set the field "markingcriteriaconsistent" to "Yes"
    And I set the field "markersmarkingconsistent" to "No"
    And I set the field "markersmarkingrecommendations[text]" to "My first comment"
    And I set the field "feedbackappropriate" to "No"
    And I set the field "feedbackrecommendations[text]" to "My second comment"
    And I set the field "goodpracticecomments[text]" to "<b>My third comment</b>"
    And I set the field "generalcomments[text]" to "<i>My fourth comment</i>"
    And I press "Save as draft"
    Then I should see "Statistics appraisal (Draft)"
    And I should see "Moderator One" in the "#fitem_id_user" "css_element"
    And I should see "Yes" in the "#id_representative" "css_element"
    And I should see "Yes" in the "#id_markingcriteriaconsistent" "css_element"
    And I should see "No" in the "#id_markersmarkingconsistent" "css_element"
    And the field "id_markersmarkingrecommendations" matches value "My first comment"
    And I should see "No" in the "#id_feedbackappropriate" "css_element"
    And the field "id_feedbackrecommendations" matches value "My second comment"
    And the field "id_goodpracticecomments" matches value "<p><strong>My third comment</strong></p>"
    And the field "id_generalcomments" matches value "<p><i>My fourth comment</i></p>"
    And I press "Save and finalise"
    Then I should see "Statistics appraisal (Finalised)"
    And I should see "Yes" in the "#id_representative" "css_element"
    And I press "Remove appraisal"
    And I press "Continue"
    And I should see "-" in the "#fitem_id_user" "css_element"

  Scenario: A non-manager should be able to see the read-only view of the appraisal form after it's been finalised
    Given I am on the "Coursework-A" "coursework activity" page logged in as "moderator1"
    When I follow "Grading Audit"
    And I set the field "representative" to "Yes"
    And I set the field "markingcriteriaconsistent" to "Yes"
    And I set the field "markersmarkingconsistent" to "No"
    And I set the field "markersmarkingrecommendations[text]" to "My first comment"
    And I set the field "feedbackappropriate" to "No"
    And I set the field "feedbackrecommendations[text]" to "My second comment"
    And I set the field "goodpracticecomments[text]" to "<b>My third comment</b>"
    And I set the field "generalcomments[text]" to "<i>My fourth comment</i>"
    And I press "Save and finalise"
    Given I am on the "Coursework-A" "coursework activity" page logged in as "teacher1"
    When I follow "Grading Audit"
    And I should see "Moderator One" in the "#mod_coursework_appraisal_user" "css_element"
    And I should see "Yes" in the "#mod_coursework_appraisal_representative" "css_element"
    And I should see "My fourth comment" in the "#mod_coursework_appraisal_generalcomments" "css_element"
