@mod @mod_coursework @javascript @_file_upload

Feature: Upload feedback files

  Scenario: Upload empty zip should occur without error
    Given there is a course
    And there is a coursework
    And there is a student
    And there is a manager
    And the student has a submission
    And the submission is finalised
    And I log in as a manager
    And I visit the coursework page
    And I click on "Upload" "button"
    And I click on "Upload feedback files in a zip" "link"
    And I upload "mod/coursework/tests/fixtures/empty.zip" file to "Feedback zip file" filemanager
    And I click on "Upload feedback zip" "button"
    Then I should see "The zip file uploaded has been processed. The results are shown below"
    