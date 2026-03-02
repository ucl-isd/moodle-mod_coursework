@mod @mod_coursework
Feature: Late submissions

    As a teacher
    I want to be able to allow stuents to submit work past the deadline
    So that they can still get some credit even if their grades get capped

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And I am logged in as a student

  Scenario: not allowed to submit late if the setting does not allow it
    Given the coursework "allowlatesubmissions" setting is "0" in the database
    And the submission deadline has passed
    When I am on the "Coursework" "coursework activity" page
    Then I should not see "Upload your submission"

  @javascript @_file_upload
  Scenario: allowed to submit late if the setting allows it
    Given the coursework "allowlatesubmissions" setting is "1" in the database
    And the submission deadline has passed
    When I am on the "Coursework" "coursework activity" page
    Then I should see "Upload your submission"
    When I follow "Upload your submission"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    Then I should be on the coursework page
