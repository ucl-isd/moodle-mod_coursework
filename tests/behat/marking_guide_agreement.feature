@mod @mod_coursework @mod_coursework_marking_guide_agreement
Feature: Marking guide
  Users can make use of the marking guide to submit grades for final approval.

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers            | 2          |
      | deadline                   | ##yesterday## |
    And there is a teacher
    And there is another teacher
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

    And I log in as "admin"
    And I am on the "Coursework" "coursework activity" page

    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking form |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | A criteria     | Description for students | Description for markers | 100           |
    And I press "Save marking guide and make it ready"

    And I am on the "Coursework" "coursework activity" page

    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I grade by filling the marking guide with:
      | A criteria | 6 | Grader one likes it |
    And I press "Save and finalise"

    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I grade by filling the marking guide with:
      | A criteria | 8 | Grader two really likes it |
    And I press "Save and finalise"

  @javascript
  Scenario: Submit final stage as marking guide and verify required grade validation.
    Given I am on the "Coursework" "coursework activity" page
    And I follow "Agree marking"
    Then the field "A criteria criterion remark" matches value ""

    And I should see "A criteria"
    And I should see "6" in the "A criteria" "table_row"
    And I should see "8" in the "A criteria" "table_row"
    And I should see "Grader one likes it" in the "Feedback" "table_row"
    And I should see "Grader two really likes it" in the "Feedback" "table_row"

    When I set the field "Mark" to ""
    And I press "Save and finalise"
    Then I should not see "Changes saved"

    And I set the field "A criteria criterion remark" to "Final agreed feedback"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    Then I should see "Changes saved"
    And I should see the final agreed grade status "Ready for release"
    And I should see "10" in the "[data-behat-markstage='final_agreed']" "css_element"

    And I follow "Release the marks"
    And I press "Confirm"
    And I log out

    When I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Final agreed feedback" in the ".coursework-feedback .behat-criterion-remark" "css_element"

  @javascript
  Scenario: Submit final stage as simple direct grading.
    Given the coursework "finalstagegrading" setting is "1" in the database
    And I am on the "Coursework" "coursework activity" page
    And I follow "Agree marking"
    And I should not see "A criteria"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    Then I should see the final agreed grade status "Ready for release"
    And I should see "10" in the "[data-behat-markstage='final_agreed']" "css_element"
