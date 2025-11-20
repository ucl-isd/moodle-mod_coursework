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
    And I set the field "Name" to "Behat marking form"
    And I click on "Click to edit criterion name" "text"
    And I set the field "guide-criteria-NEWID1-shortname" to "A criteria"
    And I click on "Maximum score" "text"
    And I set the field "guide[criteria][NEWID1][maxscore]" to "100"
    And I press "Save marking guide and make it ready"

    And I visit the coursework page

    And I click on the add feedback button for assessor 1
    And I set the field with xpath "//input[@aria-labelledby='advancedgrading-score-label']" to "6"
    And I press "Save and finalise"

    And I click on the add feedback button for assessor 2
    And I set the field with xpath "//input[@aria-labelledby='advancedgrading-score-label']" to "8"
    And I press "Save and finalise"

  @javascript
  Scenario: Submit final stage as marking guide.
    Given I visit the coursework page
    And I follow "Agree marking"
    And I should see "A criteria"
    And I should see "6" in the "A criteria" "table_row"
    And I should see "8" in the "A criteria" "table_row"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    Then I should see the final agreed grade status "Ready for release"
    And I should see the final agreed grade as 10

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
