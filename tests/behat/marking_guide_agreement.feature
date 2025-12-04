@mod @mod_coursework
Feature: Marking guide
  Users can make use of the marking guide to submit grades for final approval.

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And the student has a submission
    And the submission is finalised
    And the coursework deadline has passed

    And I log in as "admin"
    And I visit the coursework page

    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Marking guide"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name | Behat marking form |
    And I define the following marking guide:
      | Criterion name | Description for students | Description for markers | Maximum score |
      | A criteria     | Description for students | Description for markers | 100           |
    And I press "Save marking guide and make it ready"

    And I visit the coursework page

    And I click on the add feedback button for assessor 1
    And I grade by filling the marking guide with:
      | A criteria | 6 | Grader one likes it |
    And I press "Save and finalise"

    And I click on the add feedback button for assessor 2
    And I grade by filling the marking guide with:
      | A criteria | 8 | Grader two really likes it |
    And I press "Save and finalise"

  @javascript
  Scenario: Submit final stage as marking guide.
    Given I visit the coursework page
    And I follow "Agree marking"
    Then the following fields match these values:
      | Feedback: |  |

    Given the coursework "autopopulatefeedbackcomment" setting is "1" in the database
    And I visit the coursework page
    And I follow "Agree marking"
    And I should see "A criteria"
    And I should see "6" in the "A criteria" "table_row"
    And I should see "8" in the "A criteria" "table_row"
    And I should see "Grader one likes it" in the "Feedback" "table_row"
    And I should see "Grader two really likes it" in the "Feedback" "table_row"
    And the field "Feedback:" matches multiline:
"""
Grader one likes it

Grader two really likes it
"""
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should see "Please provide a valid grade for each criterion"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    And I should not see "Please provide a valid grade for each criterion"
    Then I should see the final agreed grade status "Ready for release"
    And I should see the final agreed grade as 10
    And I follow "Release the marks"
    And I press "Confirm"
    And I log out

    When I log in as a student
    And I visit the coursework page
    Then I should see "Grader one likes it" in the ".advancedgrade td.remark" "css_element"
    And I should see "Grader two really likes it" in the ".advancedgrade td.remark" "css_element"

  @javascript
  Scenario: Submit final stage as simple direct grading.
    Given the coursework "finalstagegrading" setting is "1" in the database
    And I visit the coursework page
    And I follow "Agree marking"
    And I should not see "A criteria"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    Then I should see the final agreed grade status "Ready for release"
    And I should see the final agreed grade as 10
