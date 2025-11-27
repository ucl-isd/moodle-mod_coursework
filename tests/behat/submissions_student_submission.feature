@mod @mod_coursework @mod_coursework_student_submission
Feature: Students can submit files

    In order to submit work to my tutor for grading
    As a student who has completed some work
    I want to be able to upload it as a file to the coursework instance

  Background:
    Given there is a course
    And there is a coursework
    And I am logged in as a student

  @javascript @_file_upload
  Scenario: I upload a file and see it on the coursework page as read only
    When I visit the coursework page
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
  Scenario: As a student I cannot see a link to upload a file if I do not have the capability
    When I visit the coursework page
    And I should see submission status "Not submitted"
    And I should see "Upload your submission"
    And the following "permission overrides" exist:
      | capability                      | permission | role    | contextlevel | reference |
      | mod/coursework:submit           | Prohibit   | student | Course       | C1        |
    And I visit the coursework page
    And I should not see "Upload your submission"

  @javascript @_file_upload
  Scenario: I upload a file and save it and I see it when I come back, and cannot see an edit submission link if I do not have permission
    When I visit the coursework page
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    When I visit the course page
    And I visit the coursework page
    And I click on "Edit your submission" "link"
    Then I should see "1" elements in "Upload a file" filemanager

    # Remove capability and check cannot see link.
    And the following "permission overrides" exist:
      | capability                      | permission | role    | contextlevel | reference |
      | mod/coursework:submit           | Prohibit   | student | Course       | C1        |
    And I visit the coursework page
    And I should not see "Edit your submission"
