@mod @mod_coursework @RVC_PT_83107284 @mod_coursework_submit_on_behalf
Feature: User can submit on behalf of a student

  As a user with the capability ‘coursework:submitonbehalfofstudent’
  I can submit a file on behalf of a student.
  so that the work can be graded by the grader.
  I can see submitted files on the grading report page

  Background:
    Given there is a course
    And there is a coursework
    And there is a teacher
    And there is another teacher
    And there is a student called "John1"

  @javascript @_file_upload
  Scenario: As a manager, I upload a file and see it on the coursework page
    When I am logged in as a manager
    And I visit the coursework page
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submit on behalf" "link"
    And I wait until the page is ready
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    Then I should be on the coursework page
    And I should see "Test_document.docx" in the table row containing "John1"
    And I should see "Draft" in the table row containing "John1"

    And I press "Actions"
    And I wait until the page is ready
    And I click on "Edit submission on behalf of this student" "link"
    And I wait until the page is ready
    Then I should see "1" elements in "Upload a file" filemanager
    And I should see "Test_document.docx"

  Scenario: As a manager, I can see draft submitted file on the grading report page
    When I am logged in as a manager
    And I visit the coursework page
    Then I should not see "myfile.txt"
    When the student has a submission
    And I visit the coursework page
    Then I should see "myfile.txt" in the table row containing "John1"
    And I should see "Draft" in the table row containing "John1"

  Scenario: As a manager, I can see draft submitted file on the grading report page
    When I am logged in as a manager
    And I visit the coursework page
    And the student has a submission
    And the submission is finalised
    And I visit the coursework page
    Then I should see "myfile.txt" in the table row containing "John1"
    And I should not see "Draft" in the table row containing "John1"
