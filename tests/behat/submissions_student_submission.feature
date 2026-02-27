@mod @mod_coursework
Feature: Students can submit files

    In order to submit work to my tutor for grading
    As a student who has completed some work
    I want to be able to upload it as a file to the coursework instance

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And I am logged in as a student

  @javascript @_file_upload
  Scenario: I upload a file and see it on the coursework page as read only
    When I am on the "Coursework" "coursework activity" page
    And I should see submission status "Not submitted"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should see the file on the page
    And I should see the edit submission button
    And I should see submission status "Submitted"
    And I should see submitted date "##today##%d %B %Y##"

  @javascript @_file_upload
  Scenario: I upload a file and save it and I see it when I come back
    When I am on the "Coursework" "coursework activity" page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    When I visit the course page
    And I am on the "Coursework" "coursework activity" page
    And I click on "Edit your submission" "link"
    Then I should see "1" elements in "Upload a file" filemanager
