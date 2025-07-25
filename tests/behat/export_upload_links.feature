@mod @mod_coursework
Feature: Download and upload buttons on submissions page
  These should only appear when there are submissions
  They should contain the expected menu items corresponding the user's role

  Background:
    Given there is a course
    And there is a coursework
    And there is a teacher
    And there is a student

  # @javascript needed to hide modal.
  @javascript
  Scenario: When there are no submissions the teacher should not see an upload or download menu
    When I log in as a teacher
    And I visit the coursework page
    Then I should not see "Download"
    And I should not see "Upload"

  @javascript
  Scenario: When there is a submission the teacher should see an upload and download menu
    When the student has a submission
    And the submission is finalised
    And I log in as a teacher
    And I visit the coursework page
    Then I should see "Download"
    And I should see "Upload"

  @javascript
  Scenario: A teacher should see the expected download menu items
    When the student has a submission
    And the submission is finalised
    And I log in as a teacher
    And I visit the coursework page
    And I click on "Download" "button"
    Then I should see "Download submitted files"
    And I should see "Download grading sheet"
    But I should not see "Download grades"

  @javascript
  Scenario: An admin should see the expected download menu items
    When the student has a submission
    And the submission is finalised
    And I log in as "admin"
    And I visit the coursework page
    And I click on "Download" "button"
    Then I should see "Download submitted files"
    And I should see "Download grades"
    And I should see "Download grading sheet"

  @javascript
  Scenario: A teacher should see the expect upload menu items
    When the student has a submission
    And the submission is finalised
    And I log in as a teacher
    And I visit the coursework page
    And I click on "Upload" "button"
    Then I should see "Upload grading worksheet"
    And I should see "Upload feedback files in a zip"
