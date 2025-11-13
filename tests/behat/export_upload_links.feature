@mod @mod_coursework  @mod_coursework_export_upload_links
Feature: Download and upload buttons on submissions page
  These should only appear when there are submissions
  They should contain the expected menu items corresponding the user's role

  Background:
    Given there is a course
    And there is a coursework
    And there is a teacher
    And there is a student

  Scenario: When there are no submissions the teacher should not see an upload or download menu
    When I log in as a teacher
    And I visit the coursework page
    Then I should not see "Download"
    And I should not see "Upload"

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
    And I wait until the page is ready
    Then I should see "Submitted files"
    And I should see "Marking spreadsheet"
    But I should not see "Final marks"

  @javascript
  Scenario: An admin should see the expected download menu items
    When the student has a submission
    And the submission is finalised
    And I log in as "admin"
    And I visit the coursework page
    And I click on "Download" "button"
    And I wait until the page is ready
    Then I should see "Submitted files"
    And I should see "Final marks"
    And I should see "Marking spreadsheet"

  @javascript
  Scenario: A teacher should see the expect upload menu items
    When the student has a submission
    And the submission is finalised
    And I log in as a teacher
    And I visit the coursework page
    And I click on "Upload" "button"
    Then I should see "Marking spreadsheet"
    And I should see "Feedback files in a zip"
