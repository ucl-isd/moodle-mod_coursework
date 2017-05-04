@mod @mod_coursework @RVC_PT_83107284
Feature: User can submit on behalf of a student

  As a user with the capability ‘coursework:submitonbehalfofstudent’
  I can submit a file on behalf of a student.
  so that the work can be graded by the grader.

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And I am logged in as a manager

  @javascript
  Scenario: As a teacher, I upload a file and see it on the coursework page as read only
    When I visit the coursework page
    And I click on the new submission button for the student
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I save the submission
    Then I should be on the coursework page
    And I should see the file on the page

    When I click on the edit submission button for the student

    Then I should see "1" elements in "Upload a file" filemanager
